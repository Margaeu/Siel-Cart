<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Get the currently logged-in customer's cart.
     *
     * The cart is stored in the database and is therefore
     * permanent instead of being stored in the session.
     */
    public function getCart(): ?Cart
    {
        // Make sure a customer is logged in.
        if (! auth('customer')->check()) {
            return null;
        }

        // Get or create the customer's cart.
        return Cart::firstOrCreate([
            'customer_id' => auth('customer')->id(),
        ]);
    }

    /**
     * Add a product to the customer's cart.
     *
     * If the product already exists in the cart,
     * its quantity will be increased.
     */
    public function addItem(
        int $productId,
        ?int $variantId = null,
        int $quantity = 1
    ): array {
        $cart = $this->getCart();

        if (! $cart) {
            return [
                'success' => false,
                'message' => 'Please log in to add products to your cart.',
            ];
        }

        return $this->withLockedCart($cart, fn (Cart $lockedCart) => $this->addItemToLockedCart($lockedCart, $productId, $variantId, $quantity)
        );
    }

    /** Validate and save an addition while holding the customer's cart lock. */
    private function addItemToLockedCart(
        Cart $cart,
        int $productId,
        ?int $variantId,
        int $quantity
    ): array {
        // Find the product.
        $product = Product::find($productId);

        if (! $product || ! $product->is_active) {
            return [
                'success' => false,
                'message' => 'Product not found.',
            ];
        }

        // Determine which stock amount should be checked.
        $availableStock = $product->stock_quantity;
        $variant = null;

        if ($product->has_variants && ! $variantId) {
            return ['success' => false, 'message' => 'Please select a variant.'];
        }

        // A product sold on its own has to carry a price. products.price is
        // nullable because a product sold by variant has none of its own, so
        // a product switched back off variants can reach here with nothing to
        // charge. Letting it in would put a row in the cart that prices as
        // null, blocks checkout, and can only be removed.
        if (! $product->has_variants && $product->price === null) {
            return [
                'success' => false,
                'message' => 'This product is not available for purchase right now.',
            ];
        }

        // If a variant was selected, use the variant's stock.
        if ($variantId) {
            $variant = $product->has_variants
                ? $product->variants()->active()->find($variantId)
                : null;

            if (! $variant) {
                return [
                    'success' => false,
                    'message' => 'Product variant not found.',
                ];
            }

            $availableStock = $variant->stock_quantity;
        }

        // Quantity must always be at least 1.
        if ($quantity < 1) {
            return [
                'success' => false,
                'message' => 'Quantity must be at least 1.',
            ];
        }

        // Check if this product/variant is already in the cart.
        $cartItem = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        // Calculate the final quantity after adding.
        $newQuantity = $cartItem
            ? $cartItem->quantity + $quantity
            : $quantity;

        // Never allow the cart quantity to exceed available stock.
        if ($newQuantity > $availableStock) {
            return [
                'success' => false,
                'message' => "Only {$availableStock} item(s) are available in stock.",
            ];
        }

        // Save the cart item.
        if ($cartItem) {
            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        return [
            'success' => true,
            'message' => $this->addedToCartMessage($product, $variant),
        ];
    }

    /** Build a confirmation that identifies the exact item added. */
    private function addedToCartMessage(Product $product, ?ProductVariant $variant): string
    {
        $itemName = $product->name;

        if ($variant) {
            $itemName .= ' — '.$variant->name;
        }

        return $itemName.' has been added to your cart.';
    }

    /**
     * Change the quantity of an existing cart item.
     *
     * This validates the requested quantity against
     * the product's current stock.
     */
    public function updateQuantity(
        int $cartItemId,
        int $quantity
    ): array {
        $cart = $this->getCart();

        if (! $cart) {
            return [
                'success' => false,
                'message' => 'Please log in first.',
            ];
        }

        return $this->withLockedCart($cart, fn (Cart $lockedCart) => $this->updateQuantityInLockedCart($lockedCart, $cartItemId, $quantity)
        );
    }

    private function updateQuantityInLockedCart(Cart $cart, int $cartItemId, int $quantity): array
    {
        // Find only an item belonging to this customer's cart.
        $cartItem = $cart->items()
            ->with(['product', 'variant'])
            ->lockForUpdate()
            ->find($cartItemId);

        if (! $cartItem) {
            return [
                'success' => false,
                'message' => 'Cart item not found.',
            ];
        }

        // Quantity must be at least 1.
        if ($quantity < 1) {
            return [
                'success' => false,
                'message' => 'Quantity must be at least 1.',
            ];
        }

        // A product that is deleted, deactivated or out of stock
        // cannot have its quantity changed at all. Only removing it works.
        // Without this a deleted product with stock left on its row would
        // still accept quantity changes.
        if (! $cartItem->is_purchasable) {
            return [
                'success' => false,
                'message' => 'This product is no longer available. Please remove it from your cart.',
            ];
        }

        // Get the latest available stock. The accessor uses the variant's
        // stock when the item has a variant, and the product's otherwise.
        $availableStock = $cartItem->available_stock;

        // Do not allow the customer to request more
        // than what is currently available.
        if ($quantity > $availableStock) {
            return [
                'success' => false,
                'message' => "Only {$availableStock} item(s) are available in stock.",
            ];
        }

        $cartItem->update([
            'quantity' => $quantity,
        ]);

        return [
            'success' => true,
            'message' => 'Cart quantity updated.',
        ];
    }

    /**
     * Remove an item from the customer's cart.
     */
    public function removeItem(int $cartItemId): bool
    {
        $cart = $this->getCart();

        if (! $cart) {
            return false;
        }

        return $this->withLockedCart($cart, function (Cart $lockedCart) use ($cartItemId): bool {
            // Keep the ownership check and deletion under the same cart lock.
            $cartItem = $lockedCart->items()->lockForUpdate()->find($cartItemId);

            if (! $cartItem) {
                return false;
            }

            $cartItem->delete();

            return true;
        });
    }

    /**
     * Check whether the cart contains unavailable products.
     *
     * This is used before checkout.
     */
    public function hasUnavailableItems(): bool
    {
        $cart = $this->getCart();

        if (! $cart) {
            return false;
        }

        // CartItem decides what "unavailable" means so this check and
        // the notice shown on the cart page always use the same rule.
        return $cart->items()
            ->with(['product', 'variant'])
            ->get()
            ->contains(fn ($item) => $item->is_unavailable);
    }

    /**
     * Get the subtotal of all cart items.
     *
     * Each item is priced by CartItem's payable_subtotal accessor, which
     * uses the variant price for items with a variant and the product price
     * for items without one, and contributes nothing for a row the shop can
     * no longer sell.
     */
    public function getSubtotal(): float
    {
        $cart = $this->getCart();

        if (! $cart) {
            return 0;
        }

        return (float) $cart->items()
            ->with(['product', 'variant'])
            ->get()
            ->sum(fn ($item) => $item->payable_subtotal);
    }

    /**
     * Clear the customer's cart.
     *
     * This can be used after a successful checkout.
     */
    public function clearCart(): void
    {
        $cart = $this->getCart();

        if ($cart) {
            $this->withLockedCart($cart, function (Cart $lockedCart): void {
                $lockedCart->items()->delete();
            });
        }
    }

    /**
     * Serialize cart changes, including first additions with no item row yet.
     * Checkout locks this same cart row before reading or consuming its items.
     */
    private function withLockedCart(Cart $cart, callable $operation): mixed
    {
        return DB::transaction(function () use ($cart, $operation) {
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->where('customer_id', auth('customer')->id())
                ->lockForUpdate()
                ->firstOrFail();

            return $operation($lockedCart);
        }, attempts: 3);
    }
}
