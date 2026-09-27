<?php

namespace Tests\Feature;

use App\Livewire\HomePage;
use App\Livewire\ProductCard;
use App\Livewire\ProductDetails;
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

    public function test_best_seller_headings_follow_assigned_categories_and_category_changes(): void
    {
        $clothing = Category::factory()->create(['is_active' => true, 'name' => 'Campus Clothing']);
        $gifts = Category::factory()->create(['is_active' => true, 'name' => 'Gifts & Keepsakes']);
        $shirt = $this->eligibleProduct(['name' => 'Sielesyuan T-Shirt - Green', 'category_id' => $clothing->id]);
        $hoodie = $this->eligibleProduct(['name' => 'CLSU Hoodie', 'category_id' => $gifts->id]);
        $this->sellOnce($shirt, 5);
        $this->sellOnce($hoodie, 3);

        $assertHeadings = function (array $categories) use ($shirt, $hoodie): void {
            $component = Livewire::test(HomePage::class)
                ->assertSeeText('Best Seller')
                ->assertDontSeeText('Best Seller in');

            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
            $xpath = new \DOMXPath($document);
            $headings = fn (string $section) => array_map(
                fn (\DOMNode $node) => trim($node->textContent),
                iterator_to_array($xpath->query('//div[@aria-label="'.$section.'"]//h3')),
            );

            $this->assertSame([$categories[0], $shirt->name, $categories[1], $hoodie->name], $headings('Best sellers'));
            $this->assertSame([$shirt->name, $hoodie->name], $headings('Top picks'));
        };

        $assertHeadings(['Campus Clothing', 'Gifts & Keepsakes']);

        $clothing->update(['name' => 'University Apparel']);
        $accessories = Category::factory()->create(['is_active' => true, 'name' => 'Accessories']);
        $hoodie->update(['category_id' => $accessories->id]);

        $assertHeadings(['University Apparel', 'Accessories']);
    }

    /**
     * A Best Seller or Top Pick badge outranks the admin-set Featured tag, so
     * a ranked card drops "Featured" (components/storefront/product-badges,
     * $hasSalesBadge). "New" is independent of ranking and stays. These tests
     * originally asserted that every badge stacked; the badge design was then
     * changed on purpose to hide Featured beside a sales badge, and the tests
     * are updated to pin that rule rather than the old one.
     */
    public function test_ranked_card_badge_replaces_featured_but_keeps_new(): void
    {
        $product = $this->eligibleProduct(['is_featured' => true, 'name' => 'Campus Tumbler']);

        Livewire::test(ProductCard::class, ['product' => $product, 'badge' => 'best_seller'])
            ->assertSeeText('Best Seller')
            ->assertDontSeeText('Featured')
            ->assertSeeText('New');

        Livewire::test(ProductCard::class, ['product' => $product, 'badge' => 'top_pick'])
            ->assertSeeText('Top Pick')
            ->assertDontSeeText('Featured')
            ->assertSeeText('New');
    }

    /**
     * The other half of the rule: with no ranking badge in play, Featured is
     * still shown, so hiding it beside a sales badge can't quietly turn into
     * hiding it everywhere.
     */
    public function test_featured_badge_shows_on_a_card_without_a_ranking_badge(): void
    {
        $product = $this->eligibleProduct(['is_featured' => true, 'name' => 'Campus Tumbler']);

        Livewire::test(ProductCard::class, ['product' => $product])
            ->assertSeeText('Featured')
            ->assertSeeText('New')
            ->assertDontSeeText('Best Seller')
            ->assertDontSeeText('Top Pick');
    }

    public function test_product_details_show_sales_badges_and_new_but_not_featured(): void
    {
        $product = $this->eligibleProduct(['is_featured' => true, 'name' => 'Campus Tumbler']);
        $this->sellOnce($product);

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSeeText('Best Seller')
            ->assertSeeText('Top Pick')
            ->assertDontSeeText('Featured')
            ->assertSeeText('New');
    }

    public function test_product_details_show_featured_when_no_sales_badge_applies(): void
    {
        // Featured but never sold: ranks into neither list.
        $product = $this->eligibleProduct(['is_featured' => true, 'name' => 'Campus Tumbler']);

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSeeText('Featured')
            ->assertSeeText('New')
            ->assertDontSeeText('Best Seller')
            ->assertDontSeeText('Top Pick');
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
