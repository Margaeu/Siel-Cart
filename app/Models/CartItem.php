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
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
     * Get the subtotal for this cart item.
     *
     * The subtotal is calculated using the current
     * product price multiplied by the quantity.
     */
    public function getSubtotalAttribute(): float
    {
        return (float) $this->product->price * $this->quantity;
    }
}