<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    /**
     * Fields that can be filled when creating or updating
     * a cart item.
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
    ];

    /**
     * A cart item belongs to one cart.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * A cart item belongs to one product.
     *
     * Products are soft-deleted, and a soft delete does not fire the
     * database cascade, so deleting a product leaves its cart items
     * behind. Without withTrashed() the relation would resolve to null
     * and every cart row would blow up on $item->product->name.
     *
     * Loading the trashed product instead lets the cart render the row
     * properly and mark it unavailable, so the customer can see what it
     * was and remove it. isPurchasable() treats trashed as unavailable.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * A cart item may have a product variant.
     *
     * The database column is product_variant_id,
     * so we explicitly specify it here.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Whether the variant this item points at is one the shop still sells.
     *
     * Price, stock and purchasability all hang on this one question, so it
     * is answered here once. A selection fails when the product is no longer
     * sold by variant, when the row is gone, when the admin deactivated it,
     * or when it belongs to some other product.
     *
     * This is the same set the product module prices and stocks from:
     * Product::display_price takes MIN over active variants and the inStock
     * scope filters to active ones, so a deactivated variant is already
     * invisible there. The cart has to agree, or it would keep quoting a
     * price for something the product page calls unavailable.
     */
    private function hasValidVariantSelection(): bool
    {
        return (bool) $this->product?->has_variants
            && $this->variant !== null
            && $this->variant->is_active
            && $this->variant->product_id === $this->product->id;
    }

    /**
     * Get the unit price for this cart item.
     *
     * A variant has its own price, so it is used whenever the
     * customer selected one. Products without variants fall back
     * to the product's own price.
     *
     * Every place that shows or totals the cart must use this
     * accessor so the cart, the order summary and the checkout
     * always agree on the same amount.
     */
    public function getPriceAttribute(): ?float
    {
        // Missing, stale or deactivated selections must never acquire the
        // parent's price.
        if ($this->product_variant_id || $this->product?->has_variants) {
            return $this->hasValidVariantSelection()
                ? (float) $this->variant->price
                : null;
        }

        return $this->product?->price === null ? null : (float) $this->product->price;
    }

    /**
     * Get the subtotal for this cart item.
     *
     * The subtotal is the unit price multiplied by the quantity.
     */
    public function getSubtotalAttribute(): float
    {
        return ($this->price ?? 0) * $this->quantity;
    }

    /**
     * Get what this row contributes to the amount the customer would pay.
     *
     * A row the shop can no longer sell contributes nothing. It is going to
     * be removed rather than bought, so counting it would quote a total that
     * can never actually be paid.
     *
     * An over-stock row is deliberately still counted. It is on sale, and the
     * customer is expected to lower the quantity rather than remove it, so it
     * keeps pricing at the quantity they chose until they do.
     */
    public function getPayableSubtotalAttribute(): float
    {
        return $this->is_purchasable ? $this->subtotal : 0.0;
    }

    /**
     * Get how many units of this item are currently on hand.
     *
     * A variant keeps its own stock, so it is used whenever the
     * customer selected one. Products without variants fall back
     * to the product's own stock.
     *
     * A selection the shop no longer sells has no stock to offer, the same
     * way it has no price. Reading stock off a deactivated or reassigned
     * variant would let the cart size a quantity control against units that
     * are not for sale.
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->product_variant_id || $this->product?->has_variants) {
            return $this->hasValidVariantSelection()
                ? (int) $this->variant->stock_quantity
                : 0;
        }

        return (int) ($this->product?->stock_quantity ?? 0);
    }

    /**
     * Get the stock status that applies to this item.
     *
     * Availability follows the quantity of the selected item.
     */
    public function getStockStatusAttribute(): ?string
    {
        return $this->available_stock > 0 ? 'in_stock' : 'out_of_stock';
    }

    /**
     * Check whether this item is still on sale at all.
     *
     * This ignores how many the customer put in the cart. It only
     * asks whether the shop is still selling the item:
     * - the product must still be active
     * - the chosen variant, if any, must still be active
     * - there must be at least one unit on hand
     */
    public function getIsPurchasableAttribute(): bool
    {
        // The product is gone entirely. The relation loads trashed
        // products, so this only happens if the row is missing.
        if (!$this->product) {
            return false;
        }

        // The product was deleted after it was added to the cart.
        if ($this->product->trashed()) {
            return false;
        }

        // The product was deactivated after it was added to the cart.
        if (!$this->product->is_active) {
            return false;
        }

        // Variant products must use stock from a valid, active selection.
        if ($this->product->has_variants) {
            if (! $this->hasValidVariantSelection()) {
                return false;
            }
        } elseif ($this->product_variant_id) {
            return false;
        }

        return $this->price !== null && $this->available_stock > 0;
    }

    /**
     * Check whether the cart holds more of this item than is left.
     *
     * This is the one kind of problem the customer can fix without
     * removing the item, by lowering the quantity. It is only true
     * while the item is otherwise still on sale.
     */
    public function getIsOverStockAttribute(): bool
    {
        return $this->is_purchasable
            && $this->quantity > $this->available_stock;
    }

    /**
     * Check whether this cart item can be bought as it stands.
     *
     * This is the single rule used by both the cart page and
     * CartService, so the notice shown to the customer and the
     * check that blocks checkout can never disagree.
     */
    public function getIsUnavailableAttribute(): bool
    {
        return !$this->is_purchasable || $this->is_over_stock;
    }
}
