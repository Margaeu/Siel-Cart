<?php

namespace App\Models;

use App\Enums\OrderItemResolutionType;
use App\Observers\HomepageRankingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

// Rotates the homepage ranking cache when a line of a qualifying order changes.
#[ObservedBy(HomepageRankingObserver::class)]
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'variant_name',
        'product_image',
        'price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    // relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The product this line was bought from, deleted or not.
     *
     * Products are soft-deleted, so without withTrashed() the relation goes
     * null the moment one is removed from the catalogue and the order loses
     * its live fallbacks -- the image most visibly. The line still displays
     * from its own snapshot either way, but there is no reason to drop back
     * to that while the product row is still sitting there. CartItem loads
     * trashed products for the same reason.
     *
     * A force-deleted product leaves product_id null rather than deleting
     * the line, so this is null only when the product is gone for good.
     */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Refunds and exchanges recorded against this line, oldest first.
     */
    public function resolutions()
    {
        return $this->hasMany(ReturnRefundResolution::class)
            ->orderBy('processed_at')
            ->orderBy('id');
    }

    /**
     * How many of this line's units can still be refunded or exchanged.
     *
     * A refunded unit is settled for good: the money went back and so did the
     * goods. An exchanged one is not -- the customer is holding its
     * replacement, and a replacement can turn out defective too. So only
     * refunds count against the units bought, and a unit can be exchanged again
     * for as long as it has not been refunded.
     */
    public function resolvableQuantity(): int
    {
        $refunded = $this->relationLoaded('resolutions')
            ? $this->resolutions->where('type', OrderItemResolutionType::Refund)->sum('quantity')
            : $this->resolutions()->reorder()->where('type', OrderItemResolutionType::Refund->value)->sum('quantity');

        return max(0, $this->quantity - (int) $refunded);
    }

    /**
     * The picture to show for this line.
     *
     * The snapshot taken at checkout comes first, so the order keeps showing
     * what the customer actually bought. The live variant image is preferred
     * over the product's own for anything older than that snapshot, since a
     * variant line should point at its own picture rather than the parent's.
     *
     * Every view that shows an order line must use this. Reaching for
     * product->primaryImage directly skips the snapshot and the variant
     * image both, and shows nothing at all once the product is deleted.
     */
    public function getDisplayImageUrlAttribute(): ?string
    {
        return $this->product_image
            ?? $this->variant?->images->first()?->url
            ?? $this->product?->primaryImage?->url;
    }

    /**
     * Whether the product (or variant) this line was bought from can still
     * be found on the storefront today.
     *
     * This never blocks anything -- the order is already placed and paid --
     * it only tells the customer why a line looks different now than it did
     * at checkout, and it gates the "Write a Review" link, which points at
     * products.show and 404s for a trashed or inactive product the same way
     * ProductDetails::mount() does.
     */
    public function getIsUnavailableAttribute(): bool
    {
        if ($this->product === null || $this->product->trashed() || ! $this->product->is_active) {
            return true;
        }

        return $this->product_variant_id !== null
            && (! $this->variant || ! $this->variant->is_active);
    }
}
