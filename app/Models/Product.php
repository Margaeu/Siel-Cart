<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


class Product extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'manage_stock',
        'is_active',
        'is_featured',
        'has_variants',
        'views_count',
    ];

    protected $appends = ['stock_status'];

    public function getStockStatusAttribute(): string
    {
        if (! $this->has_variants) {
            return $this->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        }

        $hasStock = $this->relationLoaded('variants')
            ? $this->variants->contains(fn (ProductVariant $variant) => $variant->is_active && $variant->stock_quantity > 0)
            : $this->variants()->active()->inStock()->exists();

        return $hasStock ? 'in_stock' : 'out_of_stock';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'views_count' => 'integer',
            'manage_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'has_variants' => 'boolean',
        ];
    }
     /**
     * Scope to only active products
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to only featured products
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Scope to only in-stock products
     */
    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->where(fn (Builder $query) => $query
                ->where('has_variants', false)
                ->where('stock_quantity', '>', 0))
                ->orWhere(fn (Builder $query) => $query
                    ->where('has_variants', true)
                    ->whereHas('variants', fn (Builder $variants) => $variants->active()->inStock()));
        });
    }

    /**
     * Scope to products with low stock
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->where(fn (Builder $simple) => $simple
                ->where('has_variants', false)
                ->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orWhere(fn (Builder $variable) => $variable
                ->where('has_variants', true)
                ->whereHas('variants', fn (Builder $variants) => $variants
                    ->active()
                    ->lowStock()));
        });
    }

    /**
     * Scope to filter by category
     */
    #[Scope]
    protected function inCategory(Builder $query, int $categoryId): void
    {
        $query->where('category_id', $categoryId);
    }
    /**
     * Scope to filter by price range
     */
    #[Scope]
    protected function inPriceRange(Builder $query, float $min, float $max): void
    {
        $query->whereBetween('price', [$min, $max]);
    }

     // relationships
     public function category()
     {
         return $this->belongsTo(Category::class);
     }

     public function variants()
     {
         return $this->hasMany(ProductVariant::class);
     }

     /**
      * Every image belonging to this product, shared and variant-specific alike.
      */
     public function images()
     {
         return $this->hasMany(ProductImage::class)->orderBy('sort_order');
     }

     /**
      * Images that belong to the product itself rather than to one variant:
      * size charts, packaging shots, model displays. These stay visible no
      * matter which variant the customer selects.
      */
     public function generalImages()
     {
         return $this->hasMany(ProductImage::class)
             ->whereNull('product_variant_id')
             ->orderBy('sort_order');
     }

     /**
      * The card/thumbnail image. Scoped to the shared gallery so a variant
      * image can never be picked up as the product's representative image.
      */
     public function primaryImage()
     {
         return $this->hasOne(ProductImage::class)
             ->whereNull('product_variant_id')
             ->where('is_primary', true);
     }

     public function reviews()
     {
         return $this->hasMany(Review::class);
     }

     public function approvedReviews()
     {
         return $this->hasMany(Review::class)->where('is_approved', true);
     }

     // helper Methods

    /**
     * Mean rating over approved reviews, 0 when there are none.
     *
     * A product card reads this once per star, so a plain relation query here
     * costs five round trips per card. Prefer the aggregate a listing loaded
     * with withAvg(), then the loaded relation, and only query as a last
     * resort. The aggregate is NULL for a product with no approved reviews,
     * so test for the key rather than for a value -- `??` would send exactly
     * those products back to the database on every read.
     */
    public function getAverageRatingAttribute(): float
    {
        if (array_key_exists('approved_reviews_avg_rating', $this->attributes)) {
            return (float) ($this->attributes['approved_reviews_avg_rating'] ?? 0);
        }

        $average = $this->relationLoaded('approvedReviews')
            ? $this->approvedReviews->avg('rating')
            : $this->approvedReviews()->avg('rating');

        return (float) ($average ?? 0);
    }

    /**
     * Number of approved reviews, read from the aggregate a listing loaded
     * with withCount() where one is present. See getAverageRatingAttribute().
     */
    public function getReviewsCountAttribute(): int
    {
        if (array_key_exists('approved_reviews_count', $this->attributes)) {
            return (int) $this->attributes['approved_reviews_count'];
        }

        return $this->relationLoaded('approvedReviews')
            ? $this->approvedReviews->count()
            : $this->approvedReviews()->count();
    }

    /**
     * Load the review aggregates every product card reads.
     *
     * Listings that render cards must call this, or each card falls back to
     * per-read queries against the reviews table.
     */
    #[Scope]
    protected function withReviewAggregates(Builder $query): void
    {
        $query->withCount('approvedReviews')->withAvg('approvedReviews', 'rating');
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            /*
            if (empty($product->sku)) {
                $product->sku = 'SKU-' . strtoupper(Str::random(8));
            }
            */
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && !$product->isDirty('slug')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }
}
