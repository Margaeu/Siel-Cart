<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class ProductVariant extends Model
{
    use HasFactory;
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

    //event
    protected static function boot(){
        parent::boot();

        static::creating(function($variant){
            if (empty($variant->sku)) {
                $variant->sku = 'VAR-'. strtoupper(Str::random(8));
            }
        });
    }
}
