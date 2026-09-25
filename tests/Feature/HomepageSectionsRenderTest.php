<?php

namespace Tests\Feature;

use App\Livewire\HomePage;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The homepage's Best Sellers / Top Picks presentation: headings, contextual
 * badges, empty states, and that the same product can be ranked into both
 * sections without a Livewire duplicate-key error.
 */
class HomepageSectionsRenderTest extends TestCase
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

    private function eligibleProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'is_featured' => false,
            'has_variants' => false,
            'price' => 100,
            'stock_quantity' => 10,
            'category_id' => Category::factory()->create(['is_active' => true])->id,
        ], $attributes));
    }

    private function sellOnce(Product $product, int $quantity = 1): void
    {
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => round((float) $product->price * $quantity, 2),
        ]);
    }

    public function test_headings_render_for_both_sections(): void
    {
        Livewire::test(HomePage::class)
            ->assertSeeText('Best Sellers')
            ->assertSeeText('Top Picks');
    }

    public function test_shop_by_category_is_the_last_homepage_section_after_top_picks(): void
    {
        Livewire::test(HomePage::class)
            ->assertSeeInOrder(['Top Picks', 'Shop by Category']);
    }

    public function test_best_sellers_empty_state_renders_when_none_qualify(): void
    {
        Livewire::test(HomePage::class)
            ->assertSeeText('No Best Sellers yet.');
    }

    public function test_top_picks_empty_state_renders_when_nothing_has_sold(): void
    {
        // Highlighted and in stock, but with no completed sale: not enough.
        $this->eligibleProduct(['is_featured' => true]);

        Livewire::test(HomePage::class)
            ->assertSeeText('No Top Picks yet.');
    }

    public function test_best_seller_card_shows_best_seller_badge(): void
    {
        $product = $this->eligibleProduct();
        $this->sellOnce($product, 3);

        Livewire::test(HomePage::class)
            ->assertSeeText('Best Seller');
    }

    public function test_top_pick_card_shows_top_pick_badge(): void
    {
        $this->sellOnce($this->eligibleProduct());

        Livewire::test(HomePage::class)
            ->assertSeeText('Top Pick');
    }

    /**
     * A single product with a qualifying sale wins its category
     * (Best Sellers) and also ranks into the store-wide Top Picks list, so it
     * renders in both sections on the same page. Livewire raises its own
     * duplicate-key exception during render if the two cards share a key --
     * a successful, exception-free render is the assertion.
     */
    public function test_product_in_both_sections_does_not_collide_on_livewire_key(): void
    {
        $product = $this->eligibleProduct();
        $this->sellOnce($product, 5);

        $component = Livewire::test(HomePage::class);

        $component->assertStatus(200);
        $component->assertSeeText('Best Seller');
        $component->assertSeeText('Top Pick');
        $component->assertSeeText($product->name);
    }

    public function test_unhighlighted_product_with_sales_appears_on_homepage(): void
    {
        $this->sellOnce($this->eligibleProduct(['name' => 'Plain Notebook']), 50);

        Livewire::test(HomePage::class)
            ->assertSeeText('Plain Notebook');
    }

    public function test_inactive_products_are_absent_from_homepage_sections(): void
    {
        $inactive = $this->eligibleProduct(['is_active' => false, 'name' => 'Retired Mug']);
        $this->sellOnce($inactive, 50);

        Livewire::test(HomePage::class)
            ->assertDontSeeText('Retired Mug');
    }
}
