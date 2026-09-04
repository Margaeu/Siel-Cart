<?php

namespace App\Models;

use Closure;
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
        'is_active',
        'is_featured',
        'has_variants',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'views_count' => 'integer',
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
     * Match a simple product on its own columns, or a variable product on any
     * of its active variants.
     *
     * Every stock and price filter branches this same way and differs only in
     * the two conditions, so the branching lives here once. $simple receives
     * the query already narrowed to simple products, $variant receives the
     * variants query already narrowed to active variants.
     */
    private function eitherLevel(
        Builder $query,
        Closure $simple,
        Closure $variant
    ): void {
        $query->where(fn (Builder $query) => $query
            ->where(function (Builder $simpleProducts) use ($simple) {
                $simple($simpleProducts->where('has_variants', false));
            })
            ->orWhere(fn (Builder $variableProducts) => $variableProducts
                ->where('has_variants', true)
                ->whereHas('variants', fn (Builder $variants) => $variant($variants->active()))));
    }

    /**
     * Scope to only in-stock products
     */
    #[Scope]
    protected function inStock(Builder $query): void
    {
        $this->eitherLevel(
            $query,
            fn (Builder $products) => $products->where('stock_quantity', '>', 0),
            fn (Builder $variants) => $variants->inStock(),
        );
    }

    /**
     * Scope to products with low stock
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $this->eitherLevel(
            $query,
            fn (Builder $products) => $products
                ->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
            fn (Builder $variants) => $variants->lowStock(),
        );
    }

    #[Scope]
    protected function orderByDisplayPrice(
        Builder $query,
        string $direction = 'asc'
    ): void {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $expression = '
            CASE WHEN products.has_variants = 1 THEN (
                SELECT MIN(product_variants.price)
                FROM product_variants
                WHERE product_variants.product_id = products.id
                AND product_variants.is_active = 1
            ) ELSE products.price END
        ';

        $query
            ->orderByRaw("($expression) IS NULL ASC")
            ->orderByRaw("($expression) $direction")
            ->orderBy('products.id');
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
    protected function inPriceRange(
        Builder $query,
        float $min,
        float $max
    ): void {
        $this->eitherLevel(
            $query,
            fn (Builder $products) => $products->whereBetween('price', [$min, $max]),
            fn (Builder $variants) => $variants->whereBetween('price', [$min, $max]),
        );
    }

     // relationships
     public function category()
     {
         return $this->belongsTo(Category::class);
     }

    /**
     * Variants in the order the admin arranged them. The id breaks ties, so
     * rows left at the default sort_order of 0 still have a stable order
     * rather than whatever the database happens to return.
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order')
            ->orderBy('id');
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
    protected $appends = ['stock_status'];

    public function getStockStatusAttribute(): string
    {
        if (! $this->has_variants) {
            return $this->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        }

        return $this->hasStockedVariant() ? 'in_stock' : 'out_of_stock';
    }

    /**
     * Whether any active variant carries stock.
     *
     * stock_status is appended, so every serialization of a variable product
     * reads this. Prefer the aggregate a listing loaded with
     * withStockAggregates(), then the loaded relation, and only query as a
     * last resort -- otherwise a listing runs one exists() per row.
     */
    private function hasStockedVariant(): bool
    {
        if (array_key_exists('has_stocked_variant', $this->attributes)) {
            return (bool) $this->attributes['has_stocked_variant'];
        }

        return $this->relationLoaded('variants')
            ? $this->variants->contains(fn (ProductVariant $variant) => $variant->is_active && $variant->stock_quantity > 0)
            : $this->variants()->active()->inStock()->exists();
    }

    public function getDisplayPriceAttribute(): ?float
    {
    $price = $this->has_variants
        ? ($this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)->min('price')
            : $this->variants()->active()->min('price'))
        : $this->price;

    return $price === null ? null : (float) $price;
    }

    public function getDisplayPriceLabelAttribute(): string
    {
        $price = $this->display_price;

        if ($price === null) {
            return 'Unavailable';
        }

        return ($this->has_variants ? 'From ' : '')
            . '₱' . number_format($price, 2);
    }

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
     *
     * This accessor claims the attribute name withCount('reviews') writes to,
     * and accessors win over loaded attributes. Loading the unfiltered count
     * would therefore be discarded here *and* cost a fallback query -- count
     * approvedReviews (or withReviewAggregates()) instead.
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

    /**
     * Load the stock aggregate the appended stock_status reads.
     *
     * One subquery for the whole listing instead of an exists() per variable
     * product, and cheaper than hydrating every variant just to ask whether
     * one of them has stock. See hasStockedVariant().
     */
    #[Scope]
    protected function withStockAggregates(Builder $query): void
    {
        $query->withExists([
            'variants as has_stocked_variant' => fn (Builder $variants) => $variants->active()->inStock(),
        ]);
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
