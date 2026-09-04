<?php

namespace Tests\Feature;

use App\Livewire\CancelOrderModal;
use App\Livewire\CheckoutPage;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Livewire\Livewire;
use Tests\TestCase;

class AutomaticStockStatusTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'has_variants' => false,
            'stock_quantity' => 0,
        ], $attributes));
    }

    public function test_status_columns_are_removed_and_status_is_derived_from_quantity(): void
    {
        foreach ([Product::class, ProductVariant::class] as $model) {
            $this->assertFalse(Schema::hasColumn((new $model)->getTable(), 'stock_status'));

            foreach ([-1, 0, 1, 10] as $quantity) {
                $item = new $model(['stock_quantity' => $quantity]);
                $expected = $quantity > 0 ? 'in_stock' : 'out_of_stock';
                $this->assertSame($expected, $item->stock_status);
                $this->assertSame($expected, $item->toArray()['stock_status']);
            }
        }
    }

    public function test_variant_product_status_and_queries_ignore_parent_stock_and_inactive_variants(): void
    {
        $simple = $this->product(['stock_quantity' => 1]);
        $empty = $this->product();
        $variable = $this->product(['has_variants' => true, 'stock_quantity' => 100]);
        $this->assertSame('out_of_stock', $variable->stock_status);

        $variant = ProductVariant::factory()->for($variable)->create(['stock_quantity' => 2, 'is_active' => false]);
        $this->assertSame('out_of_stock', $variable->fresh()->stock_status);
        $variant->update(['is_active' => true]);

        $this->assertSame('in_stock', $variable->fresh()->stock_status);
        $this->assertSame('in_stock', $variable->fresh('variants')->stock_status);
        $this->assertEqualsCanonicalizing([$simple->id, $variable->id], Product::inStock()->pluck('id')->all());
        $this->assertFalse(Product::whereKey($empty->id)->inStock()->exists());
        $variable->update(['is_active' => false]);
        $this->assertSame([$simple->id], Product::active()->inStock()->pluck('id')->all());

        ProductVariant::whereKey($variant->id)->decrement('stock_quantity', 2);
        $this->assertSame('out_of_stock', $variant->fresh()->stock_status);
        $this->assertSame('out_of_stock', $variable->fresh('variants')->stock_status);
        $this->assertFalse(ProductVariant::inStock()->whereKey($variant->id)->exists());
        $this->assertFalse(Product::inStock()->whereKey($variable->id)->exists());

        ProductVariant::whereKey($variant->id)->increment('stock_quantity');
        $this->assertSame('in_stock', $variable->fresh()->stock_status);
    }

    public static function stockTypes(): array
    {
        return ['simple product' => [false], 'variant' => [true]];
    }

    #[DataProvider('stockTypes')]
    public function test_checkout_sells_last_unit_and_cancellation_restores_availability(bool $hasVariants): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $product = $this->product(['has_variants' => $hasVariants, 'stock_quantity' => $hasVariants ? 0 : 1]);
        $variant = $hasVariants
            ? ProductVariant::factory()->for($product)->create(['stock_quantity' => 1])
            : null;
        $cart = Cart::create(['customer_id' => $customer->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => 1,
        ]);

        Livewire::actingAs($customer, 'customer')->test(CheckoutPage::class)->call('placeOrder');
        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(0, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertSame('out_of_stock', $product->fresh()->stock_status);
        $this->assertFalse(Product::inStock()->whereKey($product->id)->exists());

        Livewire::test(CancelOrderModal::class, ['order' => $order])
            ->set('reason', 'change_of_mind')->call('cancelOrder')
            ->assertRedirect(route('customer.orders.show', $order->id));

        $this->assertSame(1, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertSame('in_stock', $product->fresh()->stock_status);
        $this->assertTrue(Product::inStock()->whereKey($product->id)->exists());
    }

    public function test_cart_rejects_invalid_variants_and_tracks_stock_changes(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');
        $product = $this->product(['has_variants' => true, 'stock_quantity' => 100]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 1]);
        $otherVariant = ProductVariant::factory()->create(['stock_quantity' => 5]);
        $service = app(CartService::class);

        $this->assertFalse($service->addItem($product->id)['success']);
        $this->assertFalse($service->addItem($product->id, $otherVariant->id)['success']);
        $variant->update(['is_active' => false]);
        $this->assertFalse($service->addItem($product->id, $variant->id)['success']);
        $variant->update(['is_active' => true]);
        $this->assertTrue($service->addItem($product->id, $variant->id)['success']);
        $this->assertFalse($service->addItem($product->id, $variant->id)['success']);

        ProductVariant::whereKey($variant->id)->decrement('stock_quantity');
        $this->assertTrue($service->hasUnavailableItems());
        ProductVariant::whereKey($variant->id)->increment('stock_quantity');
        $this->assertFalse($service->hasUnavailableItems());
        $variant->delete();
        $this->assertTrue($service->hasUnavailableItems());
    }

    public function test_product_details_selects_available_variant_and_updates_purchase_button(): void
    {
        $product = $this->product(['has_variants' => true]);
        $empty = ProductVariant::factory()->for($product)->create(['stock_quantity' => 0]);
        $available = ProductVariant::factory()->for($product)->create(['stock_quantity' => 2]);

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSet('selectedVariant', $available->id)
            ->assertSeeHtml('wire:click="addToCart"')
            ->call('selectVariant', $empty->id)
            ->assertDontSeeHtml('wire:click="addToCart"')
            ->call('selectVariant', $available->id)
            ->assertSeeHtml('wire:click="addToCart"');
    }

    /**
     * Render the listing after adding more variable products, and count how
     * many of the queries it costs touch product_variants.
     */
    private function variantQueriesToRenderListing(Category $category, int $extraProducts): int
    {
        for ($i = 0; $i < $extraProducts; $i++) {
            $product = $this->product(['category_id' => $category->id, 'has_variants' => true]);
            ProductVariant::factory()->for($product)->create(['stock_quantity' => 1]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        Livewire::test(ProductListing::class)->assertOk();
        $queries = array_filter(
            DB::getQueryLog(),
            fn (array $query) => str_contains($query['query'], 'product_variants'),
        );
        DB::disableQueryLog();

        return count($queries);
    }

    /**
     * Product::getStockStatusAttribute() falls back to a per-product exists()
     * query when the variants relation is not loaded, so a listing that forgets
     * to eager load variants turns into an N+1. preventLazyLoading() cannot see
     * that fallback -- it asks for the query explicitly -- so guard it by cost
     * instead: one eager load serves the page however many rows it shows.
     *
     * Scoped to variant queries on purpose. Total query count is not flat here,
     * because getReviewsCountAttribute() has the same per-row shape.
     */
    public function test_product_listing_does_not_query_variants_once_per_product(): void
    {
        $category = Category::factory()->create();

        $twoProducts = $this->variantQueriesToRenderListing($category, 2);
        $eightProducts = $this->variantQueriesToRenderListing($category, 6);

        $this->assertSame($eightProducts, $twoProducts, sprintf(
            'Rendering the listing ran %d variant queries for 2 variable products and %d for 8. '
            .'The count scales with the rows, so add variants to the eager load of the query '
            .'feeding this listing.',
            $twoProducts,
            $eightProducts,
        ));
    }

    #[DataProvider('stockTypes')]
    public function test_admin_can_edit_quantities_without_a_manual_status(bool $hasVariants): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        \Filament\Facades\Filament::setCurrentPanel('admin');
        $product = $this->product(['has_variants' => $hasVariants]);
        $variant = $hasVariants
            ? ProductVariant::factory()->for($product)->create(['stock_quantity' => 0])
            : null;
        $field = $variant ? "variants.record-{$variant->id}.stock_quantity" : 'stock_quantity';

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertOk()
            ->assertFormFieldDoesNotExist('stock_status')
            ->fillForm([$field => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertSame('in_stock', $product->fresh()->stock_status);
    }
}
