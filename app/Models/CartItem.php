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
    public function getPriceAttribute(): float
    {
        return (float) ($this->variant?->price ?? $this->product?->price ?? 0);
    }

    /**
     * Get the subtotal for this cart item.
     *
     * The subtotal is the unit price multiplied by the quantity.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get how many units of this item are currently on hand.
     *
     * A variant keeps its own stock, so it is used whenever the
     * customer selected one. Products without variants fall back
     * to the product's own stock.
     */
    public function getAvailableStockAttribute(): int
    {
        return (int) ($this->variant?->stock_quantity ?? $this->product?->stock_quantity ?? 0);
    }

    /**
     * Get the stock status that applies to this item.
     *
     * A variant carries its own status, and it is the one that
     * matters when the customer picked a variant. Only fall back
     * to the product's status when there is no variant.
     */
    public function getStockStatusAttribute(): ?string
    {
        return $this->variant?->stock_status ?? $this->product?->stock_status;
    }

    /**
     * Check whether this item is still on sale at all.
     *
     * This ignores how many the customer put in the cart. It only
     * asks whether the shop is still selling the item:
     * - the product must still be active
     * - the chosen variant, if any, must still be active
     * - it must not be flagged out of stock
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

        // The chosen variant was deactivated after it was added.
        if ($this->variant && !$this->variant->is_active) {
            return false;
        }

        // The seller flagged the item as out of stock.
        if ($this->stock_status === 'out_of_stock') {
            return false;
        }

        return $this->available_stock > 0;
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