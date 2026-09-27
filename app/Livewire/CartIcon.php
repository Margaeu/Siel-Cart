<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartIcon extends Component
{
    // Number of distinct cart items, regardless of their quantities.
    public $cartCount = 0;

    /**
     * Load the cart count when the component is first displayed.
     */
    public function mount(CartService $cartService)
    {
        $this->updateCartCount($cartService);
    }

    /**
     * Update the cart icon whenever the cart is changed.
     *
     * This listens for the "cart-updated" event dispatched
     * by ProductCard and CartPage.
     */
    #[On('cart-updated')]
    public function updateCartCount(CartService $cartService)
    {
        // Get the permanent cart from the database.
        $cart = $cartService->getCart();

        // Count each product/variant entry once, regardless of quantity.
        $this->cartCount = $cart
            ? $cart->items()->count()
            : 0;
    }

    /**
     * Display the cart icon.
     */
    public function render()
    {
        return view('livewire.cart-icon');
    }
}
