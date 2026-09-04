<?php

namespace App\Livewire;

use App\Services\CartService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CartPage extends Component
{
    /**
     * Store the customer's permanent cart.
     */
    public $cart;

    /**
     * Store an error message when quantity validation fails.
     */
    public $quantityError = null;

    /**
     * Load the customer's cart when the page opens.
     */
    public function mount(CartService $cartService)
    {
        $this->loadCart($cartService);
    }

    /**
     * Load the cart from the database.
     *
     * CartService gets the cart belonging to the
     * currently logged-in customer.
     */
    public function loadCart(CartService $cartService)
    {
        $this->cart = $cartService->getCart();

        // Load the related products and variants so the
        // Blade file can display their information.
        if ($this->cart) {
            $this->cart->load([
                'items.product.primaryImage',
                'items.product.category',
                'items.variant.images',
            ]);
        }
    }

    /**
     * Update the quantity of a cart item.
     *
     * This is used by the minus and plus quantity controls
     * on the shopping cart page.
     */
    public function updateQuantity(
        CartService $cartService,
        $cartItemId,
        $quantity
    ) {
        $this->quantityError = null;

        // Convert the input into an integer.
        $quantity = (int) $quantity;

        // Ask CartService to validate the requested quantity
        // against the product's current stock.
        $result = $cartService->updateQuantity(
            (int) $cartItemId,
            $quantity
        );

        // If stock validation fails, show the error
        // without removing the existing cart item.
        if (!$result['success']) {
            $this->quantityError = $result['message'];

            $this->loadCart($cartService);

            return;
        }

        // Reload the cart after a successful update.
        $this->loadCart($cartService);

        // Update the cart icon in the header.
        $this->dispatch('cart-updated');
    }

    /**
     * Remove an item from the customer's cart.
     */
    public function removeItem(
        CartService $cartService,
        $cartItemId
    ) {
        $removed = $cartService->removeItem(
            (int) $cartItemId
        );

        if ($removed) {
            // Reload the database cart after removing the item.
            $this->loadCart($cartService);

            // Update the cart icon.
            $this->dispatch('cart-updated');

            session()->flash(
                'success',
                'Item removed from cart.'
            );
        }
    }

    /**
     * Remove all items from the customer's cart.
     */
    public function clearCart(CartService $cartService)
    {
        $cartService->clearCart();

        // Reload the now-empty cart.
        $this->loadCart($cartService);

        // Update the cart icon.
        $this->dispatch('cart-updated');

        session()->flash(
            'success',
            'Cart cleared.'
        );
    }

    /**
     * Calculate the cart subtotal.
     *
     * Each item prices itself through CartItem's subtotal accessor,
     * so variant items use the variant price and everything else
     * uses the product price.
     */
    #[Computed]
    public function subtotal()
    {
        return $this->cart
            ? $this->cart->items->sum(fn ($item) => $item->subtotal)
            : 0;
    }

    /**
     * Check whether the cart contains an unavailable item.
     *
     * The customer will not be allowed to checkout while
     * an out-of-stock item remains in the cart.
     */
    #[Computed]
    public function hasUnavailableItems()
    {
        return $this->cart
            ? app(CartService::class)->hasUnavailableItems()
            : false;
    }

    public function render()
    {
        return view('livewire.cart-page')
            ->layout(
                'components.layouts.front-end-layout',
                ['title' => 'Shopping Cart']
            );
    }
}
