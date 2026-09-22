<?php

namespace Tests\Feature;

use App\Livewire\ProductDetails;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product page's quantity stepper lives in Alpine; the chosen quantity
 * only reaches the server as the argument to addToCart().
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
}
