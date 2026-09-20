<?php

namespace Tests\Feature;

use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListingCategoryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_filter_only_shows_the_custom_minimum_and_maximum_fields(): void
    {
        Livewire::test(ProductListing::class)
            ->assertDontSee('Under ₱300')
            ->assertDontSee('₱300 – ₱500')
            ->assertDontSee('Over ₱500')
            ->assertSeeHtml('id="price-min"')
            ->assertSeeHtml('id="price-max"');
    }

    public function test_all_products_count_stays_at_the_active_catalog_total_when_a_category_is_selected(): void
    {
        $athletics = Category::factory()->create([
            'name' => 'Athletics',
            'slug' => 'athletics',
        ]);
        $merch = Category::factory()->create([
            'name' => 'Merch',
            'slug' => 'merch',
        ]);

        Product::factory()->count(2)->for($athletics)->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 100,
        ]);
        Product::factory()->count(3)->for($merch)->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 100,
        ]);
        Product::factory()->for($merch)->create([
            'is_active' => false,
            'has_variants' => false,
            'price' => 100,
        ]);

        $component = Livewire::test(ProductListing::class);

        $this->assertSame(5, $component->viewData('allProductsCount'));
        $this->assertSame(5, $component->viewData('products')->total());

        $component->set('category', 'merch');

        $this->assertSame(5, $component->viewData('allProductsCount'));
        $this->assertSame(3, $component->viewData('products')->total());
        $component->assertSeeHtml('data-testid="all-products-count" class="text-xs text-gray-500">5</span>');
    }
}
