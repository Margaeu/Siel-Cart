<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Soft-deleted on purpose. A customer gets exactly one review per completed
 * order for a product (the unique product/customer/order index), and that
 * chance is spent whether the review stays up, is hidden (is_approved =
 * false) or is deleted by an admin after a report. A hard delete used to
 * remove the only record that the order had been reviewed, so the author
 * could post again straight away. Any check for "has this purchase already
 * been reviewed" must therefore read withTrashed() -- see
 * ProductDetails::completedOrderId() and Customer\OrderDetails.
 *
 * Everything that displays or counts reviews (product page, aggregates,
 * admin lists) goes through Eloquent, so the SoftDeletingScope keeps a
 * removed review out of all of them exactly as a hard delete did.
 */
class Review extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'customer_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'photos',
        'video_path',
        'is_verified_purchase',
        'is_approved',
    ];

    protected $casts = [
        'photos' => 'array',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
    ];

    // Accessor for photo URLs
    public function getPhotoUrlsAttribute(): array
    {
        if (empty($this->photos) || !is_array($this->photos)) {
            return [];
        }

        return array_map(function ($path) {
            return Storage::disk('r2')->url($path);
        }, $this->photos);
    }

    // Accessor for video URL
    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->video_path)) {
            return null;
        }

        return Storage::disk('r2')->url($this->video_path);
    }

    // withTrashed(): reviews outlive a deleted account and are attributed to
    // "Deleted customer" (see Customer::getNameAttribute) rather than vanishing
    // or crashing the product page on a null author.
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // withTrashed(): orders are soft-deleted, and the review's link to the
    // purchase it verifies should still resolve in the admin.
    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}