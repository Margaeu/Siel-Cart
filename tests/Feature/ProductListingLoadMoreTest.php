<?php

namespace Tests\Feature;

use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The storefront listing's "Load more": it grows the grid twelve at a time
 * without replacing what is already shown, and any new filter or sort starts
 * over from twelve. The view called a loadMore() that did not exist, so the
 * button did nothing and only the first twelve products were ever reachable.
 */
class ProductListingLoadMoreTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create(['is_active' => true]);

        // Thirty sellable simple products with distinct creation times, so
        // the default "newest" sort has one fixed order to compare against.
        $start = Carbon::parse('2026-09-01 08:00:00');

        foreach (range(1, 30) as $i) {
            Product::factory()->create([
                'category_id' => $this->category->id,
                'name' => sprintf('Listing Product %02d', $i),
                'is_active' => true,
                'is_featured' => $i % 2 === 0,
                'has_variants' => false,
                'price' => 100,
                'stock_quantity' => 10,
                'created_at' => $start->copy()->addMinutes($i),
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function shownIds(Testable $component): array
    {
        return $component->viewData('products')->pluck('id')->all();
    }

    public function test_the_first_twelve_of_thirty_are_shown_initially(): void
    {
        $component = Livewire::test(ProductListing::class);

        $this->assertCount(12, $this->shownIds($component));
        $this->assertSame(30, $component->viewData('products')->total());

        $component
            ->assertSet('visible', 12)
            ->assertSee("You've viewed 12 of 30 products", false)
            ->assertSee('Load more');
    }

    public function test_loading_more_appends_twelve_and_keeps_the_ones_already_shown(): void
    {
        $component = Livewire::test(ProductListing::class);
        $initial = $this->shownIds($component);

        $component->call('loadMore');
        $afterOneLoad = $this->shownIds($component);

        $this->assertCount(24, $afterOneLoad);
        $this->assertSame($initial, array_slice($afterOneLoad, 0, 12));
        $this->assertCount(24, array_unique($afterOneLoad));

        $component
            ->assertSet('visible', 24)
            ->assertSee("You've viewed 24 of 30 products", false)
            ->assertSee('Load more');
    }

    public function test_the_final_load_shows_everything_and_hides_the_button(): void
    {
        $component = Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->call('loadMore');

        $shown = $this->shownIds($component);

        $this->assertCount(30, $shown);
        $this->assertEqualsCanonicalizing(Product::pluck('id')->all(), $shown);
        $this->assertFalse($component->viewData('products')->hasMorePages());

        $component
            ->assertDontSee('Load more')
            ->assertDontSee("You've viewed", false);
    }

    public function test_changing_the_search_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('search', 'Listing Product')
            ->assertSet('visible', 12);
    }

    public function test_changing_the_category_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('category', $this->category->slug)
            ->assertSet('visible', 12);
    }

    public function test_changing_the_featured_filter_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('featured', '1')
            ->assertSet('visible', 12);
    }

    public function test_changing_the_sort_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('sort', 'name_asc')
            ->assertSet('visible', 12);
    }

    public function test_changing_the_stock_filter_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('inStock', true)
            ->assertSet('visible', 12);
    }

    public function test_applying_a_price_range_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->set('minPrice', '50')
            ->call('applyPriceFilter')
            ->assertSet('visible', 12);
    }

    public function test_clearing_filters_resets_to_twelve(): void
    {
        Livewire::test(ProductListing::class)
            ->call('loadMore')
            ->assertSet('visible', 24)
            ->call('clearFilters')
            ->assertSet('visible', 12);
    }

    public function test_the_client_cannot_set_the_visible_count_directly(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ProductListing::class)->set('visible', 1000);
    }
}
