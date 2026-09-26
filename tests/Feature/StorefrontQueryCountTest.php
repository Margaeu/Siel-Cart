<?php

namespace Tests\Feature;

use App\Livewire\HomePage;
use App\Livewire\ProductCard;
use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Storefront card listings cost a fixed number of queries however many
 * cards they render.
 *
 * Each homepage section is capped at eight cards, so the fixtures fill every
 * section deliberately -- featured products, a Best Seller in each of
 * several categories, and the same sellers as Top Picks -- mixing simple and
 * variable products, each with an image, so a lazy load anywhere on a card
 * shows up as a count that grows.
 *
 * A constant query count is not the same thing as bounded work: one query
 * can still return many rows. These tests pin the per-card query
 * multiplication only.
 */
class StorefrontQueryCountTest extends TestCase
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

    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function product(Category $category, bool $variable, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
            'has_variants' => $variable,
            'price' => $variable ? null : 100,
            'stock_quantity' => $variable ? 0 : 10,
        ], $attributes));

        if ($variable) {
            foreach ([120, 150] as $sortOrder => $price) {
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'price' => $price,
                    'stock_quantity' => 5,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]);
            }
        }

        ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        return $product;
    }

    private function sell(Product $product, int $quantity): void
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
            'product_variant_id' => $product->has_variants ? $product->variants()->value('id') : null,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 100,
            'quantity' => $quantity,
            'subtotal' => 100 * $quantity,
        ]);
    }

    /** One featured card and one ranked (Best Seller + Top Pick) card per new category. */
    private function addHomepageRows(int $rows, int $offset = 0): void
    {
        for ($i = $offset; $i < $offset + $rows; $i++) {
            $category = Category::factory()->create(['is_active' => true]);

            $this->product($category, $i % 2 === 1, ['is_featured' => true]);
            $this->sell($this->product($category, $i % 2 === 0), 20 - $i);
        }
    }

    public function test_homepage_query_count_does_not_grow_with_rendered_cards(): void
    {
        $this->addHomepageRows(2);

        $small = null;
        $smallQueries = $this->queryCount(function () use (&$small) {
            $small = Livewire::test(HomePage::class);
        });

        $this->assertCount(2, $small->viewData('featuredProducts'));
        $this->assertCount(2, $small->viewData('bestSellers'));
        $this->assertCount(2, $small->viewData('topPicks'));

        // Fill every section to its cap of eight.
        $this->addHomepageRows(6, offset: 2);

        $full = null;
        $fullQueries = $this->queryCount(function () use (&$full) {
            $full = Livewire::test(HomePage::class);
        });

        $this->assertCount(8, $full->viewData('featuredProducts'));
        $this->assertCount(8, $full->viewData('bestSellers'));
        $this->assertCount(8, $full->viewData('topPicks'));

        $this->assertSame($smallQueries, $fullQueries);
    }

    public function test_listing_query_count_is_the_same_for_every_load_more(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        foreach (range(1, 40) as $i) {
            $this->product($category, $i % 2 === 0);
        }

        $component = null;
        $initial = $this->queryCount(function () use (&$component) {
            $component = Livewire::test(ProductListing::class);
        });

        $to24 = $this->queryCount(fn () => $component->call('loadMore'));
        $to36 = $this->queryCount(fn () => $component->call('loadMore'));

        $this->assertCount(36, $component->viewData('products'));

        // loadMore's own requests skip mount(), so they cost less than the
        // first render -- but each costs the same whether it shows 24 or 36.
        $this->assertLessThanOrEqual($initial, $to24);
        $this->assertSame($to24, $to36);
    }

    /**
     * A card acting on its own request (Add to Cart) pays only for the model
     * Livewire restores, plus what CartService needs -- never for its image,
     * variants or reviews, however many the product has.
     */
    public function test_card_add_to_cart_cost_does_not_depend_on_the_product(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $plain = $this->product($category, false);
        $reviewed = $this->product($category, false);

        foreach (Customer::factory()->count(30)->create() as $customer) {
            Review::create([
                'product_id' => $reviewed->id,
                'customer_id' => $customer->id,
                'rating' => 4,
                'comment' => 'A perfectly ordinary review.',
                'is_approved' => true,
            ]);
        }

        $guest = fn (Product $product) => $this->queryCount(
            fn () => Livewire::test(ProductCard::class, ['product' => $product->fresh()])->call('addToCart')
        );

        // Mount-and-render is inside the measured callback here, so compare
        // the two products rather than pin an absolute number.
        $this->assertSame($guest($plain), $guest($reviewed));

        $this->actingAs(Customer::factory()->create(), 'customer');

        // The first add creates the customer's cart; take that out of the
        // comparison so both measured adds start from the same cart state.
        $primer = $this->product($category, false);
        Livewire::test(ProductCard::class, ['product' => $primer->fresh()])->call('addToCart');

        $plainCard = Livewire::test(ProductCard::class, ['product' => $plain->fresh()]);
        $reviewedCard = Livewire::test(ProductCard::class, ['product' => $reviewed->fresh()]);

        $this->assertSame(
            $this->queryCount(fn () => $plainCard->call('addToCart')),
            $this->queryCount(fn () => $reviewedCard->call('addToCart')),
        );
    }

    public function test_a_guest_add_to_cart_request_only_restores_the_model(): void
    {
        $product = $this->product(Category::factory()->create(['is_active' => true]), false);
        $card = Livewire::test(ProductCard::class, ['product' => $product->fresh()]);

        // Renderless: the card does not reload its image or aggregates just
        // to redraw identical markup before redirecting the guest to log in.
        $this->assertSame(1, $this->queryCount(fn () => $card->call('addToCart')));
    }

    /**
     * A card mounted from a bare model (no parent eager loading) still shows
     * the right image, rating and price, loading them in a fixed number of
     * queries rather than one per accessor read.
     */
    public function test_a_card_from_a_bare_model_loads_its_data_in_two_queries(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = $this->product($category, true);
        Review::create([
            'product_id' => $product->id,
            'customer_id' => Customer::factory()->create()->id,
            'rating' => 4,
            'comment' => 'A perfectly ordinary review.',
            'is_approved' => true,
        ]);

        $bare = Product::find($product->id);

        $card = null;
        $queries = $this->queryCount(function () use (&$card, $bare) {
            $card = Livewire::test(ProductCard::class, ['product' => $bare]);
        });

        $this->assertSame(2, $queries); // cardImage + one aggregate query
        $card->assertSeeText('₱120.00–₱150.00')
            ->assertSeeHtml('Rated 4 out of 5, 1 review');
    }
}
