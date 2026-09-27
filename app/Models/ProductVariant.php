<?php

namespace App\Models;

use App\Support\Name;
use App\Support\Sku;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductVariant extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['stock_status'];

    public function getStockStatusAttribute(): string
    {
        return $this->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * For a product with variants, price and stock live here and the
     * product's own columns mean nothing — so the Product log alone would
     * miss every price or stock edit on a variable product.
     *
     * Like Product's `stock_quantity`, this also records the model-instance
     * `decrement()`/`increment()` calls from checkout, restock, and
     * return/refund resolution; those rows have no admin causer.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'product_id',
                'sku',
                'name',
                'price',
                'stock_quantity',
                'low_stock_threshold',
                'is_active',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * A variant name like "Red - Large" is ambiguous on its own, so the log
     * keeps which product it belongs to — readable even after deletion.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $productName = Product::withTrashed()->whereKey($this->product_id)->value('name');

        if (filled($productName)) {
            $activity->properties = $activity->properties->put('product_name', $productName);
        }
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where('stock_quantity', '>', 0);
    }

    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('stock_quantity', '>', 0);
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // event
    protected static function boot()
    {
        parent::boot();

        // Stored trimmed but in the case the admin typed; comparisons ignore
        // case separately (see App\Support\Sku). Runs before creating, so a
        // whitespace-only SKU is blank by the time the fallback below checks.
        static::saving(function (ProductVariant $variant) {
            $variant->sku = Sku::sanitizeForStorage($variant->sku);
            // "M" and " M " are one variant to a customer reading the picker,
            // so the name is squished on the way in and compared
            // case-insensitively by the product form (see App\Support\Name).
            $variant->name = Name::sanitizeForStorage($variant->name);
        });

        static::creating(function ($variant) {
            if (empty($variant->sku)) {
                $variant->sku = 'VAR-'.strtoupper(Str::random(8));
            }
        });

        // The product_images foreign key cascades, but a database cascade
        // fires no Eloquent events: removing a variant in the product form
        // would leave its image rows unlogged and their R2 objects orphaned.
        // Deleting them per model runs ProductImage's cleanup, which waits for
        // the surrounding save to commit.
        static::deleting(function (ProductVariant $variant) {
            $variant->images()->get()->each->delete();
        });
    }
}
