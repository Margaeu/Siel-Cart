<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Price column is a computed state with a custom sort query rather than a
 * plain column, because products.price is not what a variant product sells
 * for. Nothing else covers either closure.
 */
class ProductAdminTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_table_shows_and_sorts_by_display_price(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');

        $category = Category::factory()->create();

        $simple = Product::factory()->create(['category_id' => $category->id, 'name' => 'Simple', 'has_variants' => false, 'price' => 300]);
        $variable = Product::factory()->create(['category_id' => $category->id, 'name' => 'Variable', 'has_variants' => true, 'price' => 750]);
        ProductVariant::factory()->for($variable)->create(['price' => 44.99, 'is_active' => true]);
        ProductVariant::factory()->for($variable)->create(['price' => 750, 'is_active' => true]);

        Livewire::test(ListProducts::class)
            ->assertOk()
            ->assertSee('Manage Products')
            ->assertSee('Administrator')
            ->assertSee('Products')
            ->assertSee('Manage')
            ->assertSee('Add Product')
            ->assertCanSeeTableRecords([$simple, $variable])
            ->assertSee('From ₱44.99')
            ->assertSee('₱300.00')
            ->assertDontSee('₱750.00')
            ->sortTable('price', 'asc')
            ->assertCanSeeTableRecords([$variable, $simple], inOrder: true)
            ->sortTable('price', 'desc')
            ->assertCanSeeTableRecords([$simple, $variable], inOrder: true);
    }
}
