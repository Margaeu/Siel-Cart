<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\HomepageProductRankingService;
use App\Services\ReturnRefundResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Best Sellers and Top Picks both rank the same active, in-stock eligible
 * set (admin highlighting plays no part) from a rolling 7-day window of
 * completed sales, and both only show products with at least one qualifying
 * sale. Best Sellers takes the
 * top product per category; Top Picks ranks them store-wide.
 */
class HomepageProductRankingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Asia/Manila'));

        // Authorization on the refund/exchange service is not what this test
        // is about; see BuildsResolvableOrders for the same pattern.
        Gate::before(fn () => true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function service(): HomepageProductRankingService
    {
        return app(HomepageProductRankingService::class);
    }

    private function category(array $attributes = []): Category
    {
        return Category::factory()->create(array_merge(['is_active' => true], $attributes));
    }

    /**
     * Not highlighted by default, so every test here also proves that
     * is_featured is not what gets a product into either section.
     */
    private function eligibleProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'is_featured' => false,
            'has_variants' => false,
            'price' => 100,
            'stock_quantity' => 10,
            'category_id' => $this->category()->id,
        ], $attributes));
    }

    private function order(string $status = 'completed', ?Carbon $completedAt = null): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => $status === 'completed' ? 'paid' : 'pending',
            'status' => $status,
            'completed_at' => $status === 'completed' ? ($completedAt ?? now()->subDays(2)) : null,
        ]);
    }

    private function sell(Order $order, Product $product, int $quantity = 1, ?ProductVariant $variant = null): OrderItem
    {
        $price = (float) ($variant?->price ?? $product->price ?? 100);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_sku' => $variant?->sku ?? $product->sku,
            'variant_name' => $variant?->name,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => round($price * $quantity, 2),
        ]);
    }

    public function test_active_in_stock_product_with_recent_sale_is_eligible(): void
    {
        $product = $this->eligibleProduct();
        $this->sell($this->order(), $product, 3);

        $topPicks = $this->service()->topPicks();

        $this->assertTrue($topPicks->contains('id', $product->id));
    }

    /**
     * Highlighting neither qualifies a product nor lifts its rank: the
     * best-selling unhighlighted product outranks a highlighted one that sold
     * less, in both sections.
     */
    public function test_highlighting_does_not_affect_eligibility_or_rank(): void
    {
        $category = $this->category();

        $unhighlighted = $this->eligibleProduct(['category_id' => $category->id]);
        $this->sell($this->order(), $unhighlighted, 5);

        $highlighted = $this->eligibleProduct(['category_id' => $category->id, 'is_featured' => true]);
        $this->sell($this->order(), $highlighted, 1);

        $this->assertSame($unhighlighted->id, $this->service()->topPicks()->first()->id);
        $this->assertSame(
            $unhighlighted->id,
            $this->service()->bestSellers()->firstWhere('category_id', $category->id)->id,
        );
    }

    public function test_inactive_product_is_excluded(): void
    {
        $product = $this->eligibleProduct(['is_active' => false]);
        $this->sell($this->order(), $product, 5);

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_out_of_stock_product_is_excluded(): void
    {
        $product = $this->eligibleProduct(['stock_quantity' => 0]);
        $this->sell($this->order(), $product, 5);

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_product_with_inactive_category_is_excluded(): void
    {
        // Product::boot() refuses to save an active product into an inactive
        // category, and Category::boot() refuses to deactivate a category
        // that still holds one -- so this combination cannot arise through
        // normal use. It is still worth guarding the ranking query against
        // it, so the category is forced inactive underneath the model here,
        // bypassing both hooks, the way a raw migration or an old row could.
        $category = $this->category();
        $product = $this->eligibleProduct(['category_id' => $category->id]);
        $this->sell($this->order(), $product, 5);

        DB::table('categories')->where('id', $category->id)->update(['is_active' => false]);

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_variant_product_uses_active_variant_stock(): void
    {
        $product = $this->eligibleProduct(['has_variants' => true, 'price' => null, 'stock_quantity' => 0]);

        ProductVariant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 0]);
        ProductVariant::factory()->for($product)->create(['is_active' => false, 'stock_quantity' => 50]);

        $this->assertFalse(
            $this->service()->topPicks()->contains('id', $product->id),
            'A variant product with no stocked active variant must not be eligible.',
        );

        $stockedVariant = ProductVariant::factory()->for($product)->create(['is_active' => true, 'stock_quantity' => 20]);
        $this->sell($this->order(), $product, 2, $stockedVariant);

        $this->assertTrue($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_non_completed_orders_are_ignored(): void
    {
        $product = $this->eligibleProduct();

        foreach (['pending', 'processing', 'ready_for_pickup', 'cancelled'] as $status) {
            $order = Order::create([
                'customer_id' => Customer::factory()->create()->id,
                'subtotal' => 0,
                'total' => 0,
                'payment_method' => 'cash_on_pickup',
                'payment_status' => 'pending',
                'status' => $status,
            ]);

            $this->sell($order, $product, 5);
        }

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    /**
     * A completed order is always marked paid by the one action that
     * completes it, but payment_status is independently editable on the
     * order edit form, so the two can drift. "Sold" has to require both, or
     * this would disagree with the admin's Units Sold widget, which does.
     */
    public function test_completed_but_unpaid_order_is_ignored(): void
    {
        $product = $this->eligibleProduct();

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'failed',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        $this->sell($order, $product, 5);

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_sales_older_than_seven_days_are_ignored(): void
    {
        $product = $this->eligibleProduct();
        $this->sell($this->order(completedAt: now()->subDays(8)), $product, 10);

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_a_completed_sale_exactly_seven_days_old_is_included(): void
    {
        $product = $this->eligibleProduct();
        $this->sell($this->order(completedAt: now()->subDays(7)), $product, 4);

        $entry = $this->service()->topPicks()->firstWhere('id', $product->id);

        $this->assertSame(4, $entry->units_sold);
    }

    public function test_best_sellers_returns_only_one_winner_per_category(): void
    {
        $category = $this->category();
        $a = $this->eligibleProduct(['category_id' => $category->id]);
        $b = $this->eligibleProduct(['category_id' => $category->id]);

        $this->sell($this->order(), $a, 5);
        $this->sell($this->order(), $b, 9);

        $bestSellers = $this->service()->bestSellers();
        $winnersInCategory = $bestSellers->where('category_id', $category->id);

        $this->assertCount(1, $winnersInCategory);
        $this->assertSame($b->id, $winnersInCategory->first()->id);
    }

    public function test_zero_sale_product_cannot_become_a_best_seller(): void
    {
        $product = $this->eligibleProduct();

        $this->assertFalse($this->service()->bestSellers()->contains('id', $product->id));
    }

    public function test_top_picks_ranks_products_across_categories(): void
    {
        $low = $this->eligibleProduct();
        $high = $this->eligibleProduct();

        $this->sell($this->order(), $low, 1);
        $this->sell($this->order(), $high, 10);

        $topPicks = $this->service()->topPicks();

        $this->assertSame($high->id, $topPicks->first()->id);
        $this->assertTrue($topPicks->contains('id', $low->id));
    }

    /**
     * Top Picks is ranked on completed orders alone: a highlighted product
     * nobody has bought in the window stays out, however well it is rated,
     * viewed, or however new it is.
     */
    public function test_zero_sale_product_is_excluded_from_top_picks_even_if_highlighted(): void
    {
        $withSales = $this->eligibleProduct();
        $this->sell($this->order(), $withSales, 1);

        $zeroSales = $this->eligibleProduct(['is_featured' => true, 'views_count' => 10_000]);

        $topPicks = $this->service()->topPicks();

        $this->assertTrue($topPicks->contains('id', $withSales->id));
        $this->assertFalse($topPicks->contains('id', $zeroSales->id));
    }

    public function test_fully_refunded_product_drops_out_of_top_picks(): void
    {
        $product = $this->eligibleProduct();
        $line = $this->sell($this->order(), $product, 2);

        app(ReturnRefundResolutionService::class)->recordRefund(
            $line,
            User::factory()->create(),
            [
                'reason' => 'defective',
                'quantity' => 2,
                'refund_amount' => 200,
                'processed_at' => now()->toDateString(),
            ],
        );

        $this->assertFalse($this->service()->topPicks()->contains('id', $product->id));
    }

    public function test_refunded_units_are_subtracted_from_units_sold(): void
    {
        $product = $this->eligibleProduct();
        $line = $this->sell($this->order(), $product, 5);

        app(ReturnRefundResolutionService::class)->recordRefund(
            $line,
            User::factory()->create(),
            [
                'reason' => 'defective',
                'quantity' => 2,
                'refund_amount' => 200,
                'processed_at' => now()->toDateString(),
            ],
        );

        $entry = $this->service()->topPicks()->firstWhere('id', $product->id);

        $this->assertSame(3, $entry->units_sold);
    }

    public function test_stock_quantity_does_not_influence_ranking(): void
    {
        $lowStockHighSales = $this->eligibleProduct(['stock_quantity' => 1]);
        $highStockLowSales = $this->eligibleProduct(['stock_quantity' => 500]);

        $this->sell($this->order(), $lowStockHighSales, 10);
        $this->sell($this->order(), $highStockLowSales, 1);

        $topPicks = $this->service()->topPicks();

        $this->assertSame($lowStockHighSales->id, $topPicks->first()->id);
    }
}
