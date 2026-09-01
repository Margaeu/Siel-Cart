<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

class ProductCard extends Component
{
    public Product $product;

    /**
     * Add the product to the customer's permanent cart.
     *
     * CartService handles the database cart and stock validation.
     */
    public function addToCart(CartService $cartService)
    {
        // Add one quantity of the selected product.
        // The cart is saved in the database instead of the session.
        $result = $cartService->addItem(
            $this->product->id,
            null,
            1
        );

        // If the product cannot be added because of stock
        // or another validation problem, show the error.
        if (!$result['success']) {
            session()->flash('error', $result['message']);
            return;
        }

        // Tell the cart icon that the cart contents changed.
        $this->dispatch('cart-updated');

        // Trigger the Add to Cart popup on the current page.
        // This does not redirect the customer to the cart.
        $this->dispatch(
            'cart-added',
            message: $this->product->name . ' has been added to your cart.'
        );
    }

    public function render()
    {
        return view('livewire.product-card');
    }
}