<?php

namespace Tests\Feature;

use App\Livewire\CartPage;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The cart's quantity stepper runs in Alpine and saves through
 * updateQuantity(). Once a save settles it snaps to the quantity that call
 * returns -- not to a re-rendered data attribute, because Livewire resolves
 * the call before morphing the new HTML in, so the stepper would read the
 * pre-save value and fall back to it. These pin down that the return value
 * is always what is really saved.
 */
class CartPageQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(int $stock, int $quantity): array
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 180,
            'stock_quantity' => $stock,
        ]);

        $this->actingAs($customer, 'customer');
        app(CartService::class)->addItem($product->id, null, $quantity);

        $item = app(CartService::class)->getCart()->items()->first();

        return [$customer, $item];
    }

    public function test_the_stepper_is_client_side_with_a_text_input(): void
    {
        [$customer, $item] = $this->cartWith(stock: 10, quantity: 2);

        Livewire::actingAs($customer, 'customer')
            ->test(CartPage::class)
            ->assertSeeHtml('saved: 2,')
            ->assertSeeHtml('data-max="10"')
            ->assertSeeHtml('inputmode="numeric"')
            ->assertDontSeeHtml('wire:click="updateQuantity');
    }

    public function test_a_saved_quantity_is_returned_to_the_stepper(): void
    {
        [$customer, $item] = $this->cartWith(stock: 10, quantity: 2);

        Livewire::actingAs($customer, 'customer')
            ->test(CartPage::class)
            ->call('updateQuantity', $item->id, 7)
            ->assertReturned(7)
            ->assertSet('quantityError', null)
            ->assertDispatched('cart-updated');

        $this->assertSame(7, $item->fresh()->quantity);
    }

    public function test_a_rejected_quantity_keeps_the_saved_value_and_explains(): void
    {
        [$customer, $item] = $this->cartWith(stock: 3, quantity: 2);

        Livewire::actingAs($customer, 'customer')
            ->test(CartPage::class)
            ->call('updateQuantity', $item->id, 9)
            ->assertReturned(2)
            ->assertSet('quantityError', 'Only 3 item(s) are available in stock.');

        $this->assertSame(2, $item->fresh()->quantity);
    }
}
