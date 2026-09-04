<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin form hides the whole pricing tab for a product sold by variant,
 * and Filament does not submit hidden fields. products.price therefore never
 * reaches the insert for those products, which is why the column is nullable
 * -- with a NOT NULL column the create page died on an integrity constraint
 * and no variable product could be made through the admin at all.
 */
class ProductAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    public function test_admin_creates_a_product_sold_by_variant_without_a_product_price(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Baybayin Hoodie',
                'category_id' => $category->id,
                'has_variants' => true,
                'variants' => [
                    ['name' => 'Small', 'sku' => 'HOOD-S', 'price' => 150, 'stock_quantity' => 4, 'low_stock_threshold' => 1, 'is_active' => true],
                    ['name' => 'Large', 'sku' => 'HOOD-L', 'price' => 200, 'stock_quantity' => 2, 'low_stock_threshold' => 1, 'is_active' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Baybayin Hoodie')->with('variants')->firstOrFail();

        $this->assertNull($product->price, 'the pricing tab is hidden for variant products, so no product price is submitted');
        $this->assertSame(2, $product->variants->count());

        // A null product price must never surface to a customer: the card and
        // the listing read the cheapest active variant instead.
        $this->assertSame('From ₱150.00', $product->display_price_label);
        $this->assertSame('in_stock', $product->stock_status);
        $this->assertTrue(Product::inStock()->whereKey($product->id)->exists());
        $this->assertTrue(Product::inPriceRange(100, 175)->whereKey($product->id)->exists());
    }

    public function test_admin_creates_a_simple_product_with_its_own_price(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'CLSU Lanyard',
                'category_id' => $category->id,
                'sku' => 'LANY-001',
                'price' => 99.5,
                'stock_quantity' => 5,
                'low_stock_threshold' => 3,
                'has_variants' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'CLSU Lanyard')->firstOrFail();

        $this->assertSame('99.50', (string) $product->price);
        $this->assertSame('₱99.50', $product->display_price_label);
    }

    /**
     * The repeater's drag handle only appears to work unless orderColumn()
     * writes sort_order, and the relation only honours it if it orders by it.
     */
    public function test_variants_keep_the_order_they_were_arranged_in(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'CLSU Cap',
                'category_id' => $category->id,
                'has_variants' => true,
                'variants' => [
                    ['name' => 'Green', 'sku' => 'CAP-G', 'price' => 100, 'stock_quantity' => 1, 'low_stock_threshold' => 10, 'is_active' => true],
                    ['name' => 'White', 'sku' => 'CAP-W', 'price' => 100, 'stock_quantity' => 1, 'low_stock_threshold' => 10, 'is_active' => true],
                    ['name' => 'Black', 'sku' => 'CAP-B', 'price' => 100, 'stock_quantity' => 1, 'low_stock_threshold' => 10, 'is_active' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $variants = Product::where('name', 'CLSU Cap')->firstOrFail()->variants;

        $this->assertSame(['Green', 'White', 'Black'], $variants->pluck('name')->all());

        // Filament numbers repeater positions from 1; the seeded rows start at
        // 0. sort_order is only ever compared within one product, so the two
        // bases coexist without affecting any ordering.
        $this->assertSame([1, 2, 3], $variants->pluck('sort_order')->all());
    }

    public function test_low_stock_threshold_defaults_to_ten_so_alerts_are_on(): void
    {
        $category = Category::factory()->create();

        // low_stock_threshold is left untouched: whatever the form defaults to
        // is what every product created through the admin will carry.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'CLSU Pen',
                'category_id' => $category->id,
                'sku' => 'PEN-001',
                'price' => 25,
                'stock_quantity' => 4,
                'has_variants' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'CLSU Pen')->firstOrFail();

        $this->assertSame(10, $product->low_stock_threshold);
        $this->assertTrue(
            Product::lowStock()->whereKey($product->id)->exists(),
            'a product created with the default threshold should reach the dashboard low-stock count',
        );
    }

    public function test_price_stays_required_for_a_simple_product(): void
    {
        $category = Category::factory()->create();

        // Nullable in the database is not permission to leave the field blank
        // where the form does show it.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'CLSU Sticker',
                'category_id' => $category->id,
                'sku' => 'STIC-001',
                'price' => null,
                'stock_quantity' => 5,
                'has_variants' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['price' => 'required']);

        $this->assertDatabaseMissing('products', ['name' => 'CLSU Sticker']);
    }
}
