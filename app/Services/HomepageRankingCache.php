<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The shared cache behind HomepageProductRankingService's sales map (product
 * id => net units sold and distinct orders over the rolling window).
 *
 * Only the sales map is cached. Eligibility, stock, prices and category
 * activity are applied live on every call by the ranking service, so a
 * product-side change never needs to invalidate anything here.
 *
 * Generations, not counters. GENERATION_KEY holds a random, never reused
 * token; the map is stored under SALES_KEY together with the token it was
 * computed under, and is only ever used when that token is still current.
 * Invalidating writes a fresh token, so:
 *  - a request that started computing before an invalidation writes its
 *    map stamped with the old token, which no reader accepts any more -- it
 *    can cost the next reader a recompute, but can never be served as
 *    current;
 *  - losing the generation key (expiry, eviction, cache:clear) only ever
 *    produces another new token -- an integer counter restarting at 1 could
 *    match a surviving entry stamped "1".
 *
 * Both keys are read with one Cache::many() call. On the database store
 * (production's documented default) that is a single query, so a hit costs
 * one round trip -- the same as the sales query it replaces, minus that
 * query's aggregation work. Two separate reads (token, then an entry keyed
 * by it) would have cost more round trips than they saved.
 *
 * Consistency policy: a request that read the generation before an
 * invalidation's token write may still finish with the snapshot it had.
 * Every request that reads the generation after that write recomputes from
 * committed data. There is deliberately no recheck/retry loop. Window-edge
 * expiry (a sale ageing past seven days) can lag by up to SALES_TTL_SECONDS.
 *
 * The cache is an optimization only. Any cache failure is reported and the
 * sales map is computed directly, so rankings stay correct and only speed is
 * lost. If the post-commit token write itself fails, a stale map can be
 * served for at most SALES_TTL_SECONDS.
 *
 * Production's cache store is not verified (docs/azure-deployment.md says
 * CACHE_STORE is unset, i.e. `database`), so nothing here depends on
 * Cache::add() being atomic: two initializers that each keep their own token
 * cost one extra recompute, never stale data.
 */
class HomepageRankingCache
{
    public const GENERATION_KEY = 'homepage-ranking:generation';

    public const SALES_KEY = 'homepage-ranking:sales';

    /**
     * How long a computed sales map is reused. Bounds how far the cached
     * window can lag behind "now" (sales ageing out), and the staleness left
     * if an invalidation's token write fails.
     */
    public const SALES_TTL_SECONDS = 300;

    /**
     * Long enough that the token isn't needlessly rotated, and explicit
     * because Cache::add() with a null TTL falls back to a non-atomic
     * get-then-put in Illuminate\Cache\Repository::add(). With a TTL it
     * reaches the store's own add() where one exists (insertOrIgnore for the
     * database store; the array store has none and falls back anyway).
     */
    private const GENERATION_TTL_SECONDS = 60 * 60 * 24 * 7;

    /**
     * @param  Closure(): array<int, array{units_sold: int, distinct_orders_count: int}>  $compute
     * @return array<int, array{units_sold: int, distinct_orders_count: int}>
     */
    public function salesMap(Closure $compute): array
    {
        // Never share a map computed inside an open transaction: the sales
        // query would see that transaction's uncommitted rows, and a cached
        // copy would outlive a rollback. Reading is skipped too, so the
        // transaction sees its own writes.
        if (DB::transactionLevel() > 0) {
            return $compute();
        }

        try {
            $cached = Cache::many([self::GENERATION_KEY, self::SALES_KEY]);
            $token = $cached[self::GENERATION_KEY] ?? null;
            $entry = $cached[self::SALES_KEY] ?? null;

            if (! is_string($token)) {
                $token = $this->initializeToken();
            }
        } catch (Throwable $e) {
            report($e);

            return $compute();
        }

        if ($token === null) {
            return $compute();
        }

        if (is_array($entry)
            && ($entry['generation'] ?? null) === $token
            && is_array($entry['sales'] ?? null)) {
            return $entry['sales'];
        }

        $sales = $compute();

        try {
            Cache::put(self::SALES_KEY, ['generation' => $token, 'sales' => $sales], self::SALES_TTL_SECONDS);
        } catch (Throwable $e) {
            report($e);
        }

        return $sales;
    }

    /**
     * Start a new generation, so the next reader recomputes from committed
     * data. The previous map stays in the cache but is never accepted again.
     *
     * Called after commit by HomepageRankingObserver. Anything that changes
     * orders, order items or refund resolutions without firing Eloquent
     * events -- a query-builder mass update, a raw statement -- must call
     * this itself (after its transaction commits), or rankings stay stale
     * for up to SALES_TTL_SECONDS.
     */
    public function invalidate(): void
    {
        try {
            Cache::put(self::GENERATION_KEY, $this->newToken(), self::GENERATION_TTL_SECONDS);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Create the generation token on a cold cache, or null when the cache
     * cannot provide one (the caller then computes uncached).
     *
     * Re-read after add() so concurrent initializers converge on whichever
     * token won, where the store's add() is atomic.
     */
    private function initializeToken(): ?string
    {
        Cache::add(self::GENERATION_KEY, $this->newToken(), self::GENERATION_TTL_SECONDS);

        $token = Cache::get(self::GENERATION_KEY);

        return is_string($token) ? $token : null;
    }

    /**
     * Protected so a test can interleave a competing initializer at the
     * exact moment a new token is minted.
     */
    protected function newToken(): string
    {
        return (string) Str::ulid();
    }
}
