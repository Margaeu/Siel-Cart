<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartIcon extends Component
{
    // Number of items currently in the customer's cart.
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

        // Count the total quantity of all cart items.
        $this->cartCount = $cart
            ? $cart->total_quantity
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