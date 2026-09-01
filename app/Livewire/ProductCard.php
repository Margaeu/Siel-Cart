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
        // A product card has no variant picker, so a product sold by
        // variant cannot be added from here. Adding it would store an
        // item with no variant, which would then be priced from the
        // product instead of the variant and validated against the
        // product's stock instead of the variant's.
        //
        // Send the customer to the product page to choose a variant.
        if ($this->product->has_variants) {
            return $this->redirect(
                route('products.show', $this->product->slug),
                navigate: true
            );
        }

        // A cart belongs to a customer account, so guests are sent
        // to the login page first. After logging in they are returned
        // to this product's page to continue.
        if (!auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to add products to your cart.');

            return $this->redirect(route('login'));
        }

        // Add one quantity of the selected product.
        // The cart is saved in the database instead of the session.
        $result = $cartService->addItem(
            $this->product->id,
            null,
            1
        );

        // If the product cannot be added because of stock
        // or another validation problem, show the error in the
        // popup instead of a flash message the page never renders.
        if (!$result['success']) {
            $this->dispatch(
                'cart-error',
                message: $result['message']
            );

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