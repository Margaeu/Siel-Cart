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
        'stock_status',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
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
        $query->where('stock_status', 'in_stock')
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
