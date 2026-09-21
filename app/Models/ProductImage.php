<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductImage extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'image_path',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Uploading, removing, or reordering gallery photos never touches a column
     * on `products`, so without this the Product log is silent about them.
     * The product form's savers go through `updateOrCreate()` and per-model
     * `delete()`, so every change fires a model event; a mass
     * `->images()->delete()` would bypass it and must not replace them.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'product_id',
                'product_variant_id',
                'image_path',
                'alt_text',
                'is_primary',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * An image has no name of its own, so the log keeps the product's name
     * alongside the ids — it stays readable after the product is deleted.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $productName = Product::withTrashed()->whereKey($this->product_id)->value('name');

        if (filled($productName)) {
            $activity->properties = $activity->properties->put('product_name', $productName);
        }
    }

    #[Scope]
    protected function primary(Builder $query): void
    {
        $query->where('is_primary', true);
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // Helper Methods
    public function getUrlAttribute()
    {
        return Storage::disk('r2')->url($this->image_path);
}
}