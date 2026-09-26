<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRefundResolution;
use App\Models\User;
use App\Services\HomepageProductRankingService;
use App\Services\HomepageRankingCache;
use App\Services\ReturnRefundResolutionService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * The homepage ranking's shared sales-map cache: generation handling,
 * after-commit invalidation, and the rule that nothing computed inside an
 * open transaction is shared.
 *
 * DatabaseMigrations, not RefreshDatabase. RefreshDatabase wraps every test
 * in a transaction and only emulates commits (its transaction manager runs
 * after-commit callbacks at level 1), so it can prove neither what happens on
 * a real commit or rollback nor the open-transaction bypass -- under it,
 * every read is inside a transaction and the cache is never used at all.
 * Here saves really commit or roll back, on phpunit.xml's in-memory sqlite
 * connection and array cache, so no development or production data is
 * touched.
 *
 * Every invalidation is driven through a real model write, so it is the
 * observer wiring under test -- the invalidator is never called by hand.
 *
 * Concurrency cases are deterministic interleavings inside one process;
 * genuine multi-process races are not reproducible in PHPUnit.
 */
class HomepageRankingCacheTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Asia/Manila'));

        // Authorization on the refund/exchange service is not under test.
        Gate::before(fn () => true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A fresh service per call: its per-instance memo must not hide the cache. */
    private function rankedIds(): array
    {
        return app(HomepageProductRankingService::class)->topPicks()->pluck('id')->all();
    }

    private function token(): ?string
    {
        return Cache::get(HomepageRankingCache::GENERATION_KEY);
    }

    /** @return array{generation: string, sales: array}|null the stored entry, whatever its generation */
    private function entry(): ?array
    {
        return Cache::get(HomepageRankingCache::SALES_KEY);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 100,
            'stock_quantity' => 50,
            'category_id' => Category::factory()->create(['is_active' => true])->id,
        ]);
    }

    private function order(string $status = 'completed', string $paymentStatus = 'paid'): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now()->subDay() : null,
        ]);
    }

    private function sell(Order $order, Product $product, int $quantity = 2): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 100,
            'quantity' => $quantity,
            'subtotal' => 100 * $quantity,
        ]);
    }

    /** An order that doesn't count yet: processing, unpaid. */
    private function pendingSale(Product $product): Order
    {
        $order = $this->order('processing', 'pending');
        $this->sell($order, $product);

        return $order;
    }

    /** @return array<string, mixed> the attributes that make an order count */
    private function completion(): array
    {
        return ['status' => 'completed', 'payment_status' => 'paid', 'completed_at' => now()->subHour()];
    }

    /** Count writes of the generation key while $callback runs. */
    private function generationWrites(callable $callback): int
    {
        $writes = 0;

        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
            if ($event->key === HomepageRankingCache::GENERATION_KEY) {
                $writes++;
            }
        });

        $callback();

        return $writes;
    }

    // ---------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------

    public function test_a_read_caches_the_sales_map_under_the_generation_token(): void
    {
        $product = $this->product();
        $this->sell($this->order(), $product);

        $this->assertSame([$product->id], $this->rankedIds());

        $token = $this->token();
        $this->assertIsString($token);
        $this->assertSame($token, $this->entry()['generation']);
        $this->assertArrayHasKey($product->id, $this->entry()['sales']);

        // A second, independent reader is served from the cache: the sales
        // query (the only one touching order_items) does not run again.
        DB::enableQueryLog();
        $this->assertSame([$product->id], $this->rankedIds());
        $salesQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'order_items'));
        DB::disableQueryLog();

        $this->assertCount(0, $salesQueries);
    }

    /**
     * On the database store -- production's documented default -- a warm
     * read must cost one cache query, no more than the sales query it
     * replaces. Reading the token and then a token-keyed entry would cost
     * two round trips to save one.
     */
    public function test_a_warm_read_on_the_database_store_is_one_cache_query_and_no_sales_query(): void
    {
        config(['cache.default' => 'database']);
        Cache::clearResolvedInstances();
        Cache::forgetDriver('database');

        $product = $this->product();
        $this->sell($this->order(), $product);

        $this->assertSame([$product->id], $this->rankedIds());

        DB::enableQueryLog();
        $this->assertSame([$product->id], $this->rankedIds());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(1, $queries->filter(fn (string $sql) => str_contains($sql, '"cache"')));
        $this->assertCount(0, $queries->filter(fn (string $sql) => str_contains($sql, 'order_items')));
    }

    // ---------------------------------------------------------------
    // After-commit invalidation (real commits)
    // ---------------------------------------------------------------

    public function test_a_committed_status_change_rotates_the_generation(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        $this->assertSame([], $this->rankedIds());
        $before = $this->token();

        // updateStatus() runs in its own transaction, which really commits.
        $order->updateStatus('completed', attributes: ['payment_status' => 'paid', 'completed_at' => now()->subHour()]);

        $this->assertNotSame($before, $this->token());
        $this->assertSame([$product->id], $this->rankedIds());
    }

    public function test_a_rolled_back_change_leaves_the_generation_and_entry_untouched(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        $this->assertSame([], $this->rankedIds());
        $before = $this->token();
        $entry = $this->entry();

        try {
            DB::transaction(function () use ($order) {
                $order->update($this->completion());

                throw new RuntimeException('roll back');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->token());
        $this->assertSame($entry, $this->entry());
        $this->assertSame([], $this->rankedIds());
    }

    public function test_nested_transactions_defer_invalidation_to_the_outer_commit(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        $this->rankedIds();
        $before = $this->token();

        DB::transaction(function () use ($order, $before) {
            DB::transaction(fn () => $order->update($this->completion()));

            // The inner commit is only a savepoint release.
            $this->assertSame($before, $this->token());
        });

        $this->assertNotSame($before, $this->token());
        $this->assertSame([$product->id], $this->rankedIds());
    }

    public function test_a_write_outside_any_transaction_invalidates_immediately(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        $this->rankedIds();
        $before = $this->token();

        $this->assertSame(0, DB::transactionLevel());
        $order->update($this->completion());

        $this->assertNotSame($before, $this->token());
        $this->assertSame([$product->id], $this->rankedIds());
    }

    public function test_force_deleting_an_order_invalidates_despite_cascaded_children(): void
    {
        $product = $this->product();
        $order = $this->order();
        $line = $this->sell($order, $product, 3);

        app(ReturnRefundResolutionService::class)->recordRefund($line, User::factory()->create(), [
            'reason' => 'defective',
            'quantity' => 1,
            'refund_amount' => 100,
            'processed_at' => now()->toDateString(),
        ]);

        $this->assertSame([$product->id], $this->rankedIds());
        $before = $this->token();

        $order->forceDelete();

        // The database cascade removed the line and its refund without
        // firing their events; the order's own event did the invalidating.
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, ReturnRefundResolution::count());
        $this->assertNotSame($before, $this->token());
        $this->assertSame([], $this->rankedIds());
    }

    public function test_soft_delete_and_restore_each_invalidate(): void
    {
        $product = $this->product();
        $order = $this->order();
        $this->sell($order, $product);

        $this->assertSame([$product->id], $this->rankedIds());
        $afterSale = $this->token();

        $order->delete();

        $this->assertNotSame($afterSale, $this->token());
        $this->assertSame([], $this->rankedIds());
        $afterDelete = $this->token();

        $order->restore();

        $this->assertNotSame($afterDelete, $this->token());
        $this->assertSame([$product->id], $this->rankedIds());
    }

    public function test_a_recorded_refund_invalidates_and_an_exchange_does_not(): void
    {
        $product = $this->product();
        $line = $this->sell($this->order(), $product, 3);
        $admin = User::factory()->create();
        $service = app(ReturnRefundResolutionService::class);

        $this->rankedIds();
        $before = $this->token();

        $service->recordExchange($line, $admin, [
            'reason' => 'defective',
            'quantity' => 1,
            'processed_at' => now()->toDateString(),
        ]);

        $this->assertSame($before, $this->token());

        $service->recordRefund($line, $admin, [
            'reason' => 'defective',
            'quantity' => 3,
            'refund_amount' => 300,
            'processed_at' => now()->toDateString(),
        ]);

        $this->assertNotSame($before, $this->token());
        $this->assertSame([], $this->rankedIds());
    }

    public function test_resolutions_remain_immutable(): void
    {
        $line = $this->sell($this->order(), $this->product(), 2);
        $resolution = app(ReturnRefundResolutionService::class)->recordRefund($line, User::factory()->create(), [
            'reason' => 'defective',
            'quantity' => 1,
            'refund_amount' => 100,
            'processed_at' => now()->toDateString(),
        ]);

        try {
            $resolution->update(['quantity' => 2]);
            $this->fail('A resolution was updated.');
        } catch (LogicException) {
            // expected
        }

        try {
            $resolution->delete();
            $this->fail('A resolution was deleted.');
        } catch (LogicException) {
            // expected
        }

        $this->assertSame(1, $resolution->fresh()->quantity);
    }

    // ---------------------------------------------------------------
    // Change capture across several saves in one transaction
    // ---------------------------------------------------------------

    public function test_a_relevant_save_followed_by_an_irrelevant_one_invalidates_once_after_commit(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);
        $this->rankedIds();

        $writes = $this->generationWrites(function () use ($order) {
            DB::transaction(function () use ($order) {
                $order->update($this->completion());
                // By commit time this second save has replaced the model's
                // change set; the first save's relevance was captured already.
                $order->update(['admin_notes' => 'Collected by the buyer.']);

                $this->assertSame(1, DB::transactionLevel());
            });
        });

        $this->assertSame(1, $writes);
        $this->assertSame([$product->id], $this->rankedIds());
    }

    public function test_a_transaction_of_only_irrelevant_saves_does_not_invalidate(): void
    {
        $order = $this->pendingSale($this->product());
        $this->rankedIds();
        $before = $this->token();

        $writes = $this->generationWrites(function () use ($order) {
            DB::transaction(function () use ($order) {
                $order->update(['admin_notes' => 'Called the customer.']);
                $order->update(['admin_notes' => 'Called again.']);
            });
        });

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->token());
    }

    public function test_relevant_and_irrelevant_saves_that_roll_back_do_not_invalidate(): void
    {
        $order = $this->pendingSale($this->product());
        $this->rankedIds();
        $before = $this->token();

        $writes = $this->generationWrites(function () use ($order) {
            try {
                DB::transaction(function () use ($order) {
                    $order->update($this->completion());
                    $order->update(['admin_notes' => 'Collected.']);

                    throw new RuntimeException('roll back');
                });
            } catch (RuntimeException) {
                // expected
            }
        });

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->token());
    }

    public function test_a_rolled_back_savepoint_does_not_invalidate_when_the_outer_transaction_commits(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);
        $other = $this->pendingSale($this->product());
        $this->rankedIds();
        $before = $this->token();

        DB::transaction(function () use ($order, $other) {
            try {
                DB::transaction(function () use ($order) {
                    $order->update($this->completion());

                    throw new RuntimeException('roll back the savepoint');
                });
            } catch (RuntimeException) {
                // expected
            }

            $other->update(['admin_notes' => 'Unrelated edit that does commit.']);
        });

        $this->assertSame($before, $this->token());
        $this->assertSame([], $this->rankedIds());
    }

    // ---------------------------------------------------------------
    // Nothing computed inside an open transaction is shared
    // ---------------------------------------------------------------

    public function test_a_ranking_inside_an_open_transaction_neither_reads_nor_writes_the_cache(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        $this->assertSame([], $this->rankedIds());
        $token = $this->token();
        $entry = $this->entry();

        $salesWrites = 0;
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$salesWrites) {
            if ($event->key === HomepageRankingCache::SALES_KEY) {
                $salesWrites++;
            }
        });

        DB::beginTransaction();

        try {
            $order->update($this->completion());

            // Inside the transaction the uncommitted sale is visible, and a
            // cached read would have hidden it -- so this proves the cache
            // was not read either.
            $this->assertSame([$product->id], $this->rankedIds());
        } finally {
            DB::rollBack();
        }

        $this->assertSame(0, $salesWrites);
        $this->assertSame($token, $this->token());
        $this->assertSame($entry, $this->entry());
        $this->assertSame([], $this->rankedIds());
    }

    // ---------------------------------------------------------------
    // Generation safety
    // ---------------------------------------------------------------

    public function test_concurrent_initializers_converge_on_the_winning_token(): void
    {
        $this->sell($this->order(), $this->product());

        // Creating a completed order already started a generation (through
        // the observer); start from none, as a cold cache would.
        Cache::forget(HomepageRankingCache::GENERATION_KEY);

        // A competing request wins Cache::add() at the exact moment this one
        // mints its own token.
        $this->app->bind(HomepageRankingCache::class, fn () => new class extends HomepageRankingCache
        {
            protected function newToken(): string
            {
                Cache::add(self::GENERATION_KEY, 'winner-token', 3600);

                return 'loser-token';
            }
        });

        $this->rankedIds();

        $this->assertSame('winner-token', $this->token());
        $this->assertSame('winner-token', $this->entry()['generation']);
    }

    /**
     * A reader that is mid-computation when a change commits may return its
     * earlier snapshot (the documented policy), but it can only write to its
     * own, now-dead generation. The next reader recomputes under the new one.
     */
    public function test_a_reader_racing_a_committed_change_cannot_populate_the_new_generation(): void
    {
        $product = $this->product();
        $order = $this->pendingSale($product);

        // Initialize the generation without warming an entry.
        Cache::put(HomepageRankingCache::GENERATION_KEY, 'old-token', 3600);

        $fired = false;
        DB::listen(function ($query) use (&$fired, $order) {
            if ($fired || ! str_contains($query->sql, 'order_items') || ! str_contains($query->sql, 'refunded_quantity')) {
                return;
            }

            // The sales query has returned its (pre-change) rows. Now the
            // change commits, as if from another request.
            $fired = true;
            $order->update($this->completion());
        });

        $inFlight = $this->rankedIds();

        $this->assertTrue($fired);
        $this->assertSame([], $inFlight, 'The in-flight reader keeps its snapshot.');

        // The in-flight result was stored, but stamped with the generation
        // it was computed under -- which is no longer current.
        $newToken = $this->token();
        $this->assertNotSame('old-token', $newToken);
        $this->assertSame('old-token', $this->entry()['generation']);

        // So the next reader rejects it, recomputes, and sees the change.
        $this->assertSame([$product->id], $this->rankedIds());
        $this->assertSame($newToken, $this->entry()['generation']);
    }

    public function test_losing_the_generation_key_never_revives_a_stale_entry(): void
    {
        $product = $this->product();
        $this->sell($this->order(), $product);

        $this->rankedIds();
        $old = $this->token();

        // A stale map survives, stamped with the old token -- claiming a
        // product that never sold -- while the generation key itself is lost.
        Cache::put(HomepageRankingCache::SALES_KEY, [
            'generation' => $old,
            'sales' => [999999 => ['units_sold' => 99, 'distinct_orders_count' => 9]],
        ], 300);
        Cache::forget(HomepageRankingCache::GENERATION_KEY);

        $this->assertSame([$product->id], $this->rankedIds());
        $this->assertNotSame($old, $this->token());
        $this->assertSame($this->token(), $this->entry()['generation']);
        $this->assertArrayNotHasKey(999999, $this->entry()['sales']);
    }

    public function test_a_flushed_cache_starts_a_new_generation(): void
    {
        $product = $this->product();
        $this->sell($this->order(), $product);

        $this->rankedIds();
        $old = $this->token();

        Cache::flush();

        $this->assertSame([$product->id], $this->rankedIds());
        $this->assertNotSame($old, $this->token());
    }

    public function test_a_failing_cache_still_ranks_correctly_and_reports(): void
    {
        Exceptions::fake();

        $product = $this->product();
        $this->sell($this->order(), $product);

        Cache::swap(new Repository(new class extends ArrayStore implements Store
        {
            public function get($key): mixed
            {
                throw new RuntimeException('cache down');
            }

            public function put($key, $value, $seconds): bool
            {
                throw new RuntimeException('cache down');
            }
        }));

        $this->assertSame([$product->id], $this->rankedIds());

        Exceptions::assertReported(RuntimeException::class);
    }

    // ---------------------------------------------------------------
    // Window expiry and TTL
    // ---------------------------------------------------------------

    public function test_a_sale_ages_out_once_the_window_passes_and_the_ttl_expires(): void
    {
        $product = $this->product();
        $order = $this->order();
        $order->update(['completed_at' => now()->subDays(7)->addMinutes(2)]);
        $this->sell($order, $product);

        $this->assertSame([$product->id], $this->rankedIds());

        // Three minutes on, the sale is outside the window but the cached
        // map is still inside its TTL: the documented lag.
        Carbon::setTestNow(now()->addMinutes(3));
        $this->assertSame([$product->id], $this->rankedIds());

        // Past the TTL, the map is recomputed and the sale is gone.
        Carbon::setTestNow(now()->addSeconds(HomepageRankingCache::SALES_TTL_SECONDS));
        $this->assertSame([], $this->rankedIds());
    }
}
