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
     *
     * Returns the quantity the cart really holds afterwards (the new one,
     * or the old one if CartService refused it), or null if the row is
     * gone. The stepper snaps to this value. It must not read it back from
     * the re-rendered data-qty: Livewire resolves this call's promise
     * before it morphs the new HTML in, so data-qty is still the pre-save
     * number at that point. Snapping to it put the stepper back at 1 after
     * a 1 -> 2 save, which left the minus button disabled.
     */
    public function updateQuantity(
        CartService $cartService,
        $cartItemId,
        $quantity
    ): ?int {
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

            return $this->savedQuantity($cartItemId);
        }

        // Reload the cart after a successful update.
        $this->loadCart($cartService);

        // Update the cart icon in the header.
        $this->dispatch('cart-updated');

        return $this->savedQuantity($cartItemId);
    }

    /**
     * The quantity of a cart row as just reloaded from the database.
     */
    protected function savedQuantity($cartItemId): ?int
    {
        return $this->cart?->items->firstWhere('id', (int) $cartItemId)?->quantity;
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
     * Each item prices itself through CartItem's payable_subtotal accessor,
     * so variant items use the variant price and everything else uses the
     * product price. A row the shop can no longer sell adds nothing, so the
     * summary never quotes an amount that includes items the customer is
     * about to be told to remove.
     */
    #[Computed]
    public function subtotal()
    {
        return $this->cart
            ? $this->cart->items->sum(fn ($item) => $item->payable_subtotal)
            : 0;
    }

    /**
     * How many units the subtotal above actually covers.
     *
     * Cart::total_quantity counts everything in the cart, which is what the
     * header badge wants -- a broken row still needs the customer's
     * attention. The summary line has to agree with the money beside it
     * instead, so it counts only what is being priced.
     */
    #[Computed]
    public function payableQuantity()
    {
        return $this->cart
            ? $this->cart->items
                ->filter(fn ($item) => $item->is_purchasable)
                ->sum('quantity')
            : 0;
    }

    /**
     * How many rows were left out of the subtotal, so the summary can say
     * so rather than silently showing a smaller number than the cart.
     */
    #[Computed]
    public function excludedItemCount()
    {
        return $this->cart
            ? $this->cart->items
                ->reject(fn ($item) => $item->is_purchasable)
                ->count()
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
