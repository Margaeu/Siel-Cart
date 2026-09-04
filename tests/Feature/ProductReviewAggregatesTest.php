<?php

namespace Tests\Feature;

use App\Livewire\HomePage;
use App\Livewire\ProductCard;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Product::getReviewsCountAttribute() and getAverageRatingAttribute() read
 * through the approvedReviews() relation *method*, which issues a query every
 * time regardless of what the caller eager loaded. Every product card reads
 * both, so a listing pays one or two extra queries per row.
 *
 * preventLazyLoading() cannot catch this -- the accessors ask for the query
 * explicitly -- so these tests guard the cost directly: rendering a listing
 * must not get more expensive as products are added to it.
 */
class ProductReviewAggregatesTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'is_active' => true,
            'is_featured' => false,
            'has_variants' => false,
            'stock_quantity' => 5,
        ], $attributes));
    }

    /**
     * Give a product some approved reviews, plus unapproved ones that every
     * aggregate must ignore.
     *
     * @param  list<int>  $approvedRatings
     */
    private function reviewed(array $approvedRatings, int $unapproved = 1, array $attributes = []): Product
    {
        $product = $this->product($attributes);

        foreach ($approvedRatings as $rating) {
            Review::factory()->for($product)->create(['rating' => $rating, 'is_approved' => true]);
        }

        Review::factory()->count($unapproved)->for($product)
            ->create(['rating' => 1, 'is_approved' => false]);

        return $product;
    }

    /**
     * Queries issued while running $render, ignoring everything the fixtures
     * cost to build. Pass $table to count only queries touching one table.
     */
    private function queries(callable $render, ?string $table = null): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $render();

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        if ($table !== null) {
            $log = array_filter($log, fn (array $query) => str_contains($query['query'], $table));
        }

        return count($log);
    }

    /**
     * Render a listing at two catalogue sizes and assert the query count did
     * not move. Both sizes stay under the components' own limit()/paginate()
     * caps, so any growth is per-row work rather than extra rows being shown.
     */
    private function assertFlatQueryCount(
        callable $render,
        int $small,
        int $large,
        callable $seed,
        ?string $table = null,
    ): void {
        for ($i = 0; $i < $small; $i++) {
            $seed($i);
        }
        $before = $this->queries($render, $table);

        for ($i = $small; $i < $large; $i++) {
            $seed($i);
        }
        $after = $this->queries($render, $table);

        $this->assertSame($after, $before, sprintf(
            'Rendering cost %d queries for %d products and %d for %d. The extra queries '
            .'scale with the rows, so the per-row work needs to move into the listing query.',
            $before,
            $small,
            $after,
            $large,
        ));
    }

    public function test_catalog_listing_query_count_does_not_grow_with_products(): void
    {
        $this->assertFlatQueryCount(
            render: fn () => Livewire::test(ProductListing::class)->assertOk(),
            small: 3,
            large: 9,
            seed: fn () => $this->reviewed([5, 3]),
        );
    }

    public function test_home_page_query_count_does_not_grow_with_products(): void
    {
        // New Arrivals renders its cards inline; featured cards are `lazy` and
        // only render a placeholder here. Both stay under the limit(8) cap.
        $this->assertFlatQueryCount(
            render: fn () => Livewire::test(HomePage::class)->assertOk(),
            small: 3,
            large: 6,
            seed: fn () => $this->reviewed([4]),
        );
    }

    public function test_related_products_query_count_does_not_grow_with_products(): void
    {
        $product = $this->reviewed([5]);

        $this->assertFlatQueryCount(
            render: fn () => Livewire::test(ProductDetails::class, ['slug' => $product->slug])->assertOk(),
            small: 2,
            large: 4,
            seed: fn () => $this->reviewed([4, 2]),
        );
    }

    /**
     * New Arrivals renders its cards inline, so the home page request really
     * does exercise the card view and its review reads. Featured cards sit
     * behind `lazy` and only render a placeholder here, which is why the card
     * component is also tested directly below.
     */
    public function test_home_page_renders_new_arrival_cards_inline(): void
    {
        $product = $this->reviewed([5, 4], attributes: ['is_featured' => false]);

        Livewire::test(HomePage::class)
            ->assertOk()
            ->assertSee('New Arrivals')
            ->assertSee($product->name)
            ->assertSee($this->category->name)
            ->assertSee('(2)');
    }

    /**
     * The lazy boundary means the featured card never renders during the
     * HomePage request, so exercise the card component itself to prove the
     * real render is covered too.
     */
    public function test_featured_card_renders_without_querying_per_read(): void
    {
        $product = Product::query()
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->findOrFail($this->reviewed([5, 4], attributes: ['is_featured' => true])->id);

        $queries = $this->queries(
            fn () => Livewire::test(ProductCard::class, ['product' => $product])
                ->assertOk()
                ->assertSee('(2)')
                ->assertSee($this->category->name),
            'reviews',
        );

        $this->assertSame(0, $queries, 'Rendering a card re-queried reviews despite loaded aggregates.');
    }

    /**
     * Livewire re-fetches the model on every request, dropping the aggregates
     * the parent listing loaded. The card has to restore them or every update
     * silently falls back to per-read queries.
     */
    public function test_product_card_keeps_review_data_across_a_livewire_update(): void
    {
        $product = $this->reviewed([5, 4, 3]);

        $component = Livewire::test(ProductCard::class, ['product' => $product])
            ->assertSee('(3)')
            ->assertSee($this->category->name);

        $queries = $this->queries(fn () => $component->call('$refresh')->assertOk()->assertSee('(3)'), 'reviews');

        $this->assertLessThanOrEqual(1, $queries, 'A card update should reload review aggregates in one query.');
    }

    public function test_aggregates_count_only_approved_reviews(): void
    {
        $product = $this->reviewed([5, 4, 3], unapproved: 2);

        $loaded = Product::query()
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->findOrFail($product->id);

        $this->assertSame(3, $loaded->reviews_count);
        $this->assertEqualsWithDelta(4.0, $loaded->average_rating, 0.001);

        // Same answers without the aggregates, via the fallback queries.
        $this->assertSame(3, $product->fresh()->reviews_count);
        $this->assertEqualsWithDelta(4.0, $product->fresh()->average_rating, 0.001);
    }

    public function test_product_without_reviews_reports_zero_rather_than_null(): void
    {
        $product = $this->product();
        Review::factory()->for($product)->create(['is_approved' => false]);

        foreach ([
            Product::query()->withCount('approvedReviews')->withAvg('approvedReviews', 'rating')->findOrFail($product->id),
            $product->fresh(),
        ] as $loaded) {
            $this->assertSame(0, $loaded->reviews_count);
            $this->assertNotNull($loaded->average_rating);
            $this->assertEqualsWithDelta(0.0, (float) $loaded->average_rating, 0.001);
        }
    }

    /**
     * A product with no approved reviews has a NULL average. Treating that
     * NULL as "no aggregate loaded" would send exactly the products with no
     * reviews back to the database, so guard the zero case explicitly.
     */
    public function test_repeated_reads_do_not_requery_when_aggregates_are_loaded(): void
    {
        $reviewed = $this->reviewed([5, 3]);
        $unreviewed = $this->product();

        $products = Product::query()
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->whereIn('id', [$reviewed->id, $unreviewed->id])
            ->get();

        $queries = $this->queries(function () use ($products) {
            foreach ($products as $product) {
                for ($i = 0; $i < 3; $i++) {
                    $product->reviews_count;
                    $product->average_rating;
                }
            }
        }, 'reviews');

        $this->assertSame(0, $queries, 'Reading loaded aggregates should never touch the database.');
    }

    public function test_accessors_still_work_when_aggregates_were_not_loaded(): void
    {
        $product = $this->reviewed([2, 4]);

        $queries = $this->queries(function () use ($product) {
            $fresh = $product->fresh();
            $this->assertSame(2, $fresh->reviews_count);
            $this->assertEqualsWithDelta(3.0, $fresh->average_rating, 0.001);
        }, 'reviews');

        $this->assertGreaterThan(0, $queries, 'Without aggregates the accessors must fall back to querying.');
    }

    /**
     * The variant-backed stock status has the same fallback shape, and the
     * listings eager load `variants` to avoid it. Guard that here too so a
     * change to the review aggregates cannot quietly drop it.
     */
    public function test_listing_still_loads_variants_in_one_query(): void
    {
        $this->assertFlatQueryCount(
            render: fn () => Livewire::test(ProductListing::class)->assertOk(),
            small: 2,
            large: 6,
            seed: function () {
                $product = $this->reviewed([4], attributes: ['has_variants' => true]);
                ProductVariant::factory()->for($product)->create(['stock_quantity' => 3]);
            },
            table: 'product_variants',
        );
    }
}
