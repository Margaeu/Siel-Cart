<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    /**
     * Remove image objects from R2 once the surrounding transaction commits.
     *
     * The single place product-image files are deleted: the form's gallery
     * and variant savers, variant removal, and Product::forceDelete() all end
     * here. Deleting only after commit means a rolled-back save never leaves
     * a row pointing at a missing file -- Laravel discards the callback on
     * rollback. Uploads keep their original filenames, so two rows can share
     * a path; an object is left alone while any row still references it.
     *
     * Outside a transaction the callback runs immediately.
     *
     * @param  iterable<string|null>  $paths
     */
    public static function deleteFilesAfterCommit(iterable $paths): void
    {
        $paths = collect($paths)->filter(fn ($path) => filled($path))->unique()->values();

        if ($paths->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($paths) {
            $stillReferenced = static::query()
                ->whereIn('image_path', $paths)
                ->pluck('image_path')
                ->all();

            $orphaned = $paths->diff($stillReferenced)->values()->all();

            if ($orphaned !== []) {
                Storage::disk('r2')->delete($orphaned);
            }
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function (ProductImage $image) {
            static::deleteFilesAfterCommit([$image->image_path]);
        });
    }
}