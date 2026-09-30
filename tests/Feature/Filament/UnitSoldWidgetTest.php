<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\UnitSold;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The category column and the Best Seller / Top Pick filter on the admin's
 * Units Sold widget. Both read from live state rather than a stored flag --
 * category through a join to the product a line was bought from, and the
 * filter by reusing HomepageProductRankingService, the same ranking the
 * homepage badges products with (see HomepageProductRankingServiceTest for
 * the ranking rules themselves).
 */
class UnitSoldWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsAdmin(): void
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    private function category(array $attributes = []): Category
    {
        return Category::factory()->create(array_merge(['is_active' => true], $attributes));
    }

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

    private function order(?Carbon $completedAt = null): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => $completedAt ?? now()->subDay(),
        ]);
    }

    private function sell(Order $order, Product $product, int $quantity = 1): OrderItem
    {
        $price = (float) $product->price;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => round($price * $quantity, 2),
        ]);
    }

    public function test_category_column_shows_the_product_category(): void
    {
        $category = $this->category(['name' => 'Groceries']);
        $product = $this->eligibleProduct(['category_id' => $category->id]);
        $this->sell($this->order(), $product, 2);

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)->assertSee('Groceries');
    }

    public function test_category_column_still_shows_for_a_soft_deleted_product(): void
    {
        $category = $this->category(['name' => 'Merch']);
        $product = $this->eligibleProduct(['category_id' => $category->id]);
        $this->sell($this->order(), $product, 1);
        $product->delete();

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)->assertSee('Merch');
    }

    /**
     * The admin filter uses the same one winner per category as the homepage.
     */
    public function test_highlight_filter_best_seller_returns_only_the_top_product_in_a_category(): void
    {
        $category = $this->category();
        $winner = $this->eligibleProduct(['category_id' => $category->id]);
        $runnerUp = $this->eligibleProduct(['category_id' => $category->id]);
        $third = $this->eligibleProduct(['category_id' => $category->id]);

        $this->sell($this->order(now()->subDay()), $winner, 10);
        $this->sell($this->order(now()->subDay()), $runnerUp, 5);
        $this->sell($this->order(now()->subDay()), $third, 1);

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)
            ->filterTable('highlight', 'best_seller')
            ->assertSee($winner->name)
            ->assertDontSee($runnerUp->name)
            ->assertDontSee($third->name);
    }

    /**
     * The table has a row per variant, so the winning product alone would
     * list every size. Best Seller keeps only its top-selling variant: one
     * row per category.
     */
    public function test_highlight_filter_best_seller_shows_one_variant_row_per_category(): void
    {
        $winners = [];

        foreach (['Merchandise', 'Athletics'] as $name) {
            $product = $this->eligibleProduct([
                'category_id' => $this->category(['name' => $name])->id,
                'has_variants' => true,
                'price' => null,
                'stock_quantity' => 0,
            ]);

            $top = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => "{$name}-Top", 'stock_quantity' => 5]);
            $minor = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => "{$name}-Minor", 'stock_quantity' => 5]);
            $winners[] = $top->name;

            foreach ([[$top, 9], [$minor, 3]] as [$variant, $quantity]) {
                OrderItem::create([
                    'order_id' => $this->order(now()->subDay())->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'product_sku' => $variant->sku,
                    'price' => 100,
                    'quantity' => $quantity,
                    'subtotal' => 100 * $quantity,
                ]);
            }
        }

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)
            ->filterTable('highlight', 'best_seller')
            ->assertSee($winners)
            ->assertDontSee(['Merchandise-Minor', 'Athletics-Minor']);
    }

    /**
     * Top Picks ranks store-wide instead of deduping per category, so both
     * products from the same category appear once either has a qualifying
     * sale in the ranking window.
     */
    public function test_highlight_filter_top_pick_includes_every_selling_product_in_the_window(): void
    {
        $category = $this->category();
        $top = $this->eligibleProduct(['category_id' => $category->id]);
        $alsoSelling = $this->eligibleProduct(['category_id' => $category->id]);

        $this->sell($this->order(now()->subDay()), $top, 10);
        $this->sell($this->order(now()->subDay()), $alsoSelling, 5);

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)
            ->filterTable('highlight', 'top_pick')
            ->assertSee($top->name)
            ->assertSee($alsoSelling->name);
    }

    /**
     * A sale outside the ranking's rolling 7-day window still counts toward
     * this widget's all-time totals, but the product never ranked, so it
     * must drop out once the Best Seller / Top Pick filter is applied.
     */
    public function test_highlight_filter_excludes_a_product_that_only_sold_outside_the_ranking_window(): void
    {
        $staleProduct = $this->eligibleProduct();
        $this->sell($this->order(now()->subDays(8)), $staleProduct, 20);

        $this->actingAsAdmin();

        Livewire::test(UnitSold::class)->assertSee($staleProduct->name);

        Livewire::test(UnitSold::class)
            ->filterTable('highlight', 'top_pick')
            ->assertDontSee($staleProduct->name);
    }
}
