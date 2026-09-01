<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    /**
     * Fields that can be filled when creating or updating a cart.
     */
    protected $fillable = [
        'customer_id',
    ];

    /**
     * A cart belongs to one customer.
     *
     * This makes the cart permanent and connected
     * to the customer's account instead of the session.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A cart can contain many cart items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the total number of products in the cart.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get the total price of all products in the cart.
     *
     * The price comes from the related product because
     * cart_items does not have its own price column.
     */
    public function getTotalAttribute(): float
    {
        return (float) $this->items()
            ->with('product')
            ->get()
            ->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });
    }
}