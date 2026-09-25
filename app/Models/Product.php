<?php

namespace App\Models;

use App\Support\Sku;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, LogsActivity;
    // The trait's forceDelete() is aliased rather than reached via parent::.
    // A method declared here overrides the trait's, so parent::forceDelete()
    // would resolve to Model::forceDelete() -- which only calls delete() and
    // would *soft*-delete instead. See forceDelete() below.
    use SoftDeletes {
        forceDelete as protected softDeletesForceDelete;
    }

    public const INACTIVE_CATEGORY_MESSAGE = 'This category is inactive. Choose an active category, or make the product inactive.';

    public const TYPE_LOCKED_MESSAGE = 'A product cannot be switched between simple and variant after it is created. Create a new product instead.';

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
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
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
     * Eligible for the homepage's Best Sellers / Top Picks sections: active,
     * filed under a category customers can still browse to, and currently in
     * stock. Admin highlighting (is_featured) is deliberately not required --
     * those sections are earned by completed sales, and is_featured only
     * drives the separate Featured Products section. Stock only ever gates
     * eligibility here -- ranking within it is HomepageProductRankingService's
     * job, not this scope's.
     */
    #[Scope]
    protected function eligibleForHomepage(Builder $query): void
    {
        $query->active()
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
            ->inStock();
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

    /**
     * The image used by catalogue cards.
     *
     * A shared primary image remains the first choice. If the product has no
     * shared primary image, use the first image from an active variant so a
     * sellable product does not fall back to the letter placeholder while it
     * still has usable photography.
     */
    public function cardImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where(function (Builder $images) {
                $images
                    ->where(fn (Builder $shared) => $shared
                        ->whereNull('product_variant_id')
                        ->where('is_primary', true))
                    ->orWhere(fn (Builder $variantImage) => $variantImage
                        ->whereNotNull('product_variant_id')
                        ->whereHas('variant', fn (Builder $variant) => $variant->active()));
            })
            ->orderByRaw('CASE WHEN product_variant_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id');
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
        if (! $this->has_variants) {
            return $this->price === null
                ? 'Unavailable'
                : '₱'.number_format((float) $this->price, 2);
        }

        if ($this->relationLoaded('variants')) {
            $activePrices = $this->variants
                ->where('is_active', true)
                ->pluck('price');

            $minimumPrice = $activePrices->min();
            $maximumPrice = $activePrices->max();
        } else {
            $priceRange = $this->variants()
                ->active()
                ->reorder()
                ->selectRaw('MIN(price) as minimum_price, MAX(price) as maximum_price')
                ->first();

            $minimumPrice = $priceRange?->minimum_price;
            $maximumPrice = $priceRange?->maximum_price;
        }

        if ($minimumPrice === null || $maximumPrice === null) {
            return 'Unavailable';
        }

        $minimumPrice = (float) $minimumPrice;
        $maximumPrice = (float) $maximumPrice;

        if ($minimumPrice === $maximumPrice) {
            return '₱'.number_format($minimumPrice, 2);
        }

        return '₱'.number_format($minimumPrice, 2)
            .'–₱'.number_format($maximumPrice, 2);
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

    /**
     * A storefront page view is not a modification of the product. A plain
     * increment() also writes updated_at, which made the admin "Last
     * updated" track visitors instead of edits. Quiet as well: views_count is
     * not in the activity-log allowlist, so there is nothing to record.
     */
    public function incrementViews()
    {
        static::withoutTimestamps(fn () => $this->incrementQuietly('views_count'));
    }

    /**
     * Permanently delete the product together with its variants and images,
     * and remove the image objects from R2 once the delete has committed.
     *
     * The work lives here rather than in a forceDeleting hook because
     * forceDeleteQuietly() runs this same method inside withoutEvents(), where
     * no hook would fire. On that path model and activity events are
     * suppressed on purpose, but the media still gets cleaned up: the paths
     * are captured up front and scheduled explicitly, not through events.
     *
     * The database would cascade the child rows by itself, but a cascade
     * fires no Eloquent events, so the variant and image deletes would leave
     * no activity-log rows and their R2 objects would be orphaned. Order
     * items are left to their nullOnDelete foreign keys: the lines survive
     * with their snapshots and lose only the link to the live product.
     *
     * The per-row ProductImage::delete() calls above each schedule their own
     * file cleanup and, by default, would keep a file still named by an order
     * or cart snapshot -- see ProductImage::deleteFilesAfterCommit(). Force
     * delete means gone for good, so the explicit call below runs last with
     * that protection turned off and purges every file regardless.
     */
    public function forceDelete()
    {
        return DB::transaction(function () {
            $imagePaths = ProductImage::query()
                ->where('product_id', $this->getKey())
                ->pluck('image_path');

            ProductImage::query()
                ->where('product_id', $this->getKey())
                ->get()
                ->each
                ->delete();

            $this->variants()->get()->each->delete();

            $deleted = $this->softDeletesForceDelete();

            ProductImage::deleteFilesAfterCommit($imagePaths, protectHistoricalReferences: false);

            return $deleted;
        });
    }

    /**
     * The first free slug among "base", "base-2", "base-3", ... Trashed rows
     * count: the unique index covers them, and a restored product must get
     * its own URL back.
     */
    protected static function uniqueSlug(string $base): string
    {
        $taken = static::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where('slug', $base)
                ->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')
            ->flip();

        if (! $taken->has($base)) {
            return $base;
        }

        $suffix = 2;

        while ($taken->has("{$base}-{$suffix}")) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        // The slug is set once, at creation, and never follows later renames:
        // it is the product's public URL, and regenerating it on every name
        // change broke links that customers and pages had already shared. The
        // edit form shows it read-only -- staff don't need to (or should)
        // hand-edit it -- so nothing besides this hook ever sets it.
        static::creating(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug(Str::slug((string) $product->name) ?: 'product');
            }
        });

        // Stored trimmed but in the case the admin typed; comparisons ignore
        // case separately (see App\Support\Sku). A variable product has no
        // SKU of its own, so a blank one is stored as NULL, not ''.
        static::saving(function (Product $product) {
            $product->sku = Sku::sanitizeForStorage($product->sku, blankToNull: true);
        });

        // Converting a product between simple and variant would need
        // decisions nobody has made -- what happens to the parent price, stock,
        // and SKU, and to existing variants -- so the type is fixed at
        // creation. The form disables the toggle on edit; this stops seeders,
        // tinker, or a tampered request from switching it anyway.
        static::updating(function (Product $product) {
            if ($product->isDirty('has_variants')) {
                throw ValidationException::withMessages([
                    'has_variants' => self::TYPE_LOCKED_MESSAGE,
                ]);
            }
        });

        // Trashing keeps is_active, so a product that was live when it went to
        // the trash would go straight back onto the storefront the moment it
        // was restored -- possibly with a stale price or stock count nobody
        // re-checked. Restoring always lands it inactive; the admin publishes
        // it again deliberately. A hook rather than an action callback so the
        // edit-page RestoreAction, the RestoreBulkAction, and any tinker or
        // seeder restore() all behave the same. restore() saves the model
        // after this fires, so the change is written in the same update.
        // The product half of Category::deactivationBlockedReason(): an
        // inactive category holds no active products, so a product cannot be
        // created active in one, moved into one while active, or activated
        // inside one. Only checked when one of those columns changes, so an
        // unrelated edit to a product never trips over its category.
        static::saving(function (Product $product) {
            if (! $product->is_active) {
                return;
            }

            if ($product->exists && ! $product->isDirty(['is_active', 'category_id'])) {
                return;
            }

            if (Category::query()->whereKey($product->category_id)->where('is_active', false)->exists()) {
                throw ValidationException::withMessages([
                    'category_id' => self::INACTIVE_CATEGORY_MESSAGE,
                ]);
            }
        });

        static::restoring(function (Product $product) {
            $product->is_active = false;
        });
    }
}
