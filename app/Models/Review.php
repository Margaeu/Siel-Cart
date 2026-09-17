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
        'video',
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
        if (empty($this->video)) {
            return null;
        }

        return Storage::disk('r2')->url($this->video);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}