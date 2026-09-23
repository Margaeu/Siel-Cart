<?php

namespace Tests\Feature;

use App\Livewire\ProductDetails;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product page's quantity stepper and variant picker both live in Alpine;
 * the chosen quantity and variant only reach the server as the arguments to
 * addToCart(). That makes both of them client input, so this covers what the
 * server does with a variant id it is handed rather than one it chose itself.
 */
class ProductDetailsAddToCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 10): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 180,
            'stock_quantity' => $stock,
        ]);
    }

    public function test_adds_the_quantity_passed_from_the_page(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 3)
            ->assertDispatched('cart-added')
            ->assertDispatched('cart-updated');

        $this->assertSame(3, (int) CartItem::where('product_id', $product->id)->value('quantity'));
    }

    public function test_a_non_positive_or_garbage_quantity_is_treated_as_one(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', '-4abc')
            ->assertDispatched('cart-added');

        $this->assertSame(1, (int) CartItem::where('product_id', $product->id)->value('quantity'));
    }

    public function test_more_than_the_stock_is_still_rejected_by_the_cart(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product(stock: 2);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 5)
            ->assertDispatched('cart-error')
            ->assertNotDispatched('cart-added');

        $this->assertFalse(CartItem::where('product_id', $product->id)->exists());
    }

    public function test_a_guest_is_sent_to_log_in(): void
    {
        $product = $this->product();

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 2)
            ->assertRedirect(route('login'));
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: ProductVariant}
     */
    private function variableProduct(): array
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'has_variants' => true,
            'price' => null,
            'stock_quantity' => 0,
        ]);

        // mount() opens on the first variant carrying stock, and the relation
        // orders by sort_order -- which the factory randomises, so it has to be
        // pinned here or "the one the page opened on" is a coin toss.
        $first = ProductVariant::factory()->for($product)->create([
            'name' => 'Small', 'price' => 200, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 1,
        ]);
        $second = ProductVariant::factory()->for($product)->create([
            'name' => 'Large', 'price' => 350, 'stock_quantity' => 7, 'is_active' => true, 'sort_order' => 2,
        ]);

        return [$product, $first, $second];
    }

    public function test_adds_the_variant_the_page_passes_not_the_one_it_opened_on(): void
    {
        $customer = Customer::factory()->create();
        [$product, $first, $second] = $this->variableProduct();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSet('selectedVariant', $first->id)
            ->call('addToCart', 2, $second->id)
            ->assertDispatched('cart-added');

        $item = CartItem::where('product_id', $product->id)->first();
        $this->assertSame($second->id, (int) $item->product_variant_id);
        $this->assertSame(2, (int) $item->quantity);
    }

    public function test_a_variant_belonging_to_another_product_is_refused(): void
    {
        $customer = Customer::factory()->create();
        [$product] = $this->variableProduct();
        [, $foreign] = $this->variableProduct();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 1, $foreign->id)
            ->assertDispatched('cart-error')
            ->assertNotDispatched('cart-added');

        $this->assertFalse(CartItem::query()->exists());
    }

    public function test_an_inactive_variant_is_refused(): void
    {
        $customer = Customer::factory()->create();
        [$product, $first] = $this->variableProduct();

        $hidden = ProductVariant::factory()->for($product)->create([
            'name' => 'Retired', 'price' => 10, 'stock_quantity' => 99, 'is_active' => false, 'sort_order' => 3,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 1, $hidden->id)
            ->assertDispatched('cart-error')
            ->assertNotDispatched('cart-added');

        $this->assertFalse(CartItem::query()->exists());
    }

    public function test_a_missing_variant_id_falls_back_to_the_one_the_page_rendered(): void
    {
        $customer = Customer::factory()->create();
        [$product, $first] = $this->variableProduct();

        // No JavaScript, so nothing overrides what the server selected.
        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 1)
            ->assertDispatched('cart-added');

        $this->assertSame(
            $first->id,
            (int) CartItem::where('product_id', $product->id)->value('product_variant_id')
        );
    }

    public function test_a_variant_id_sent_for_a_simple_product_is_discarded(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->product();
        [, $foreign] = $this->variableProduct();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->call('addToCart', 1, $foreign->id)
            ->assertDispatched('cart-added');

        $this->assertNull(
            CartItem::where('product_id', $product->id)->value('product_variant_id')
        );
    }
}
