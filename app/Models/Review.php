<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
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
    // "[Deleted User]" (see Customer::getNameAttribute) rather than vanishing
    // or crashing the product page on a null author.
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}