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
     * order_items.product_image and cart_items.product_image also hold this
     * exact URL, snapshotted so an order or a cart row keeps showing its
     * picture after the live image, variant, or product is gone -- a general
     * product image or a variant-specific one alike. Deleting the file out
     * from under either snapshot would turn that into a broken <img> tag, so
     * by default a path still named by either one is left alone too, the
     * same way a path another ProductImage row still uses is.
     *
     * $protectHistoricalReferences is turned off only by Product::forceDelete(),
     * whose whole point is a complete, permanent purge: order lines keep
     * their text snapshot (name, SKU, price) but are documented and tested
     * to lose the picture along with everything else. Every other caller --
     * the product form's gallery and variant image savers, and single
     * variant removal -- keeps the default, since those are routine catalog
     * edits that a cart or an unrelated order should not go blank over.
     *
     * Outside a transaction the callback runs immediately.
     *
     * @param  iterable<string|null>  $paths
     */
    public static function deleteFilesAfterCommit(iterable $paths, bool $protectHistoricalReferences = true): void
    {
        $paths = collect($paths)->filter(fn ($path) => filled($path))->unique()->values();

        if ($paths->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($paths, $protectHistoricalReferences) {
            $stillReferenced = static::query()
                ->whereIn('image_path', $paths)
                ->pluck('image_path')
                ->all();

            $stillSnapshotted = [];

            if ($protectHistoricalReferences) {
                $urlsByPath = $paths->mapWithKeys(fn ($path) => [Storage::disk('r2')->url($path) => $path]);

                $stillSnapshotted = collect()
                    ->merge(OrderItem::query()->whereIn('product_image', $urlsByPath->keys())->pluck('product_image'))
                    ->merge(CartItem::query()->whereIn('product_image', $urlsByPath->keys())->pluck('product_image'))
                    ->map(fn ($url) => $urlsByPath[$url] ?? null)
                    ->filter()
                    ->values()
                    ->all();
            }

            $orphaned = $paths->diff($stillReferenced)->diff($stillSnapshotted)->values()->all();

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
