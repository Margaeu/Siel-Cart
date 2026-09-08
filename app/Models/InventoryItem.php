<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * One row of the inventory listing: a read-only view over the two tables that
 * actually hold stock.
 *
 * Stock lives at two levels. A simple product carries its own quantity, while
 * a variable product carries none that means anything -- its variants each
 * hold their own. The admin needs to see one line per thing that can run out,
 * so this model unions both levels into a single result set. There is no
 * `inventory_items` table; that name is only the alias the union is selected
 * through, so qualified references such as `inventory_items.status` resolve.
 *
 * Nothing here is persistable. Stock is written through Product and
 * ProductVariant as before.
 */
class InventoryItem extends Model
{
    public const STATUS_OUT_OF_STOCK = 'out_of_stock';

    public const STATUS_LOW_STOCK = 'low_stock';

    public const STATUS_IN_STOCK = 'in_stock';

    /**
     * The statuses in the order the admin cares about them: whatever needs
     * restocking first. The keys are the stored values, the values the labels.
     */
    public const STATUS_LABELS = [
        self::STATUS_OUT_OF_STOCK => 'Out of Stock',
        self::STATUS_LOW_STOCK => 'Low Stock',
        self::STATUS_IN_STOCK => 'In Stock',
    ];

    protected $table = 'inventory_items';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'status_rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Every query against this model reads from the union rather than from a
     * table, so the subquery is installed here rather than at each call site.
     */
    public function newQuery(): Builder
    {
        return parent::newQuery()->fromSub(static::rowsQuery(), $this->getTable());
    }

    /**
     * A product id and a variant id can be the same number, so neither alone
     * identifies a row. The pair does, and Filament asks for the key rather
     * than for a column, so it need not exist in the union.
     */
    public function getIdAttribute(): string
    {
        return "{$this->attributes['row_type']}-{$this->attributes['source_id']}";
    }

    /**
     * Simple products and the variants of variable products, in one result set.
     */
    protected static function rowsQuery(): QueryBuilder
    {
        return static::simpleProductRows()->unionAll(static::variantRows());
    }

    /**
     * A product sold without variants is a row in its own right.
     */
    protected static function simpleProductRows(): QueryBuilder
    {
        return DB::table('products')
            ->whereNull('products.deleted_at')
            ->where('products.has_variants', false)
            ->select([
                DB::raw("'product' as row_type"),
                'products.id as source_id',
                'products.name as product_name',
                DB::raw('NULL as variant_name'),
                'products.sku as sku',
                'products.stock_quantity as stock_quantity',
                'products.low_stock_threshold as low_stock_threshold',
                DB::raw(static::booleanExpression(['products.is_active']) . ' as is_active'),
                DB::raw(static::statusExpression('products') . ' as status'),
                DB::raw(static::statusRankExpression('products') . ' as status_rank'),
            ]);
    }

    /**
     * A variable product is represented by its variants instead of itself: each
     * one has its own SKU, its own shelf and its own threshold, and the
     * product's own stock column means nothing for it.
     *
     * A variant of an inactive product is not on sale either, so the row is
     * active only when both levels are.
     */
    protected static function variantRows(): QueryBuilder
    {
        return DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.has_variants', true)
            ->select([
                DB::raw("'variant' as row_type"),
                'product_variants.id as source_id',
                'products.name as product_name',
                'product_variants.name as variant_name',
                'product_variants.sku as sku',
                'product_variants.stock_quantity as stock_quantity',
                'product_variants.low_stock_threshold as low_stock_threshold',
                DB::raw(static::booleanExpression(['products.is_active', 'product_variants.is_active']) . ' as is_active'),
                DB::raw(static::statusExpression('product_variants') . ' as status'),
                DB::raw(static::statusRankExpression('product_variants') . ' as status_rank'),
            ]);
    }

    /**
     * The status a row's stock puts it in.
     */
    protected static function statusExpression(string $table): string
    {
        return static::stockExpression($table, array_map(
            fn (string $status): string => "'{$status}'",
            array_keys(self::STATUS_LABELS),
        ));
    }

    /**
     * The same rules as a number to sort on. STATUS_LABELS is declared in the
     * order the admin wants to work through it -- whatever needs restocking
     * soonest first -- so a status's position in it is its rank.
     */
    protected static function statusRankExpression(string $table): string
    {
        return static::stockExpression($table, array_map(
            strval(...),
            range(0, count(self::STATUS_LABELS) - 1),
        ));
    }

    /**
     * The one place the stock rules live, emitting $results in the order
     * out of stock, low stock, in stock.
     *
     * These are the conditions Product and ProductVariant already scope by. An
     * empty shelf is out of stock whatever the threshold says, and because low
     * stock is only reached after that, a threshold of 0 can never match --
     * which is exactly what a threshold of 0 is meant to express.
     *
     * @param  array{0: string, 1: string, 2: string}  $results
     */
    protected static function stockExpression(string $table, array $results): string
    {
        [$outOfStock, $lowStock, $inStock] = $results;

        return "CASE
            WHEN {$table}.stock_quantity = 0 THEN {$outOfStock}
            WHEN {$table}.stock_quantity <= {$table}.low_stock_threshold THEN {$lowStock}
            ELSE {$inStock}
        END";
    }

    /**
     * The AND of the given boolean columns as a plain 1 or 0, so both halves
     * of the union agree on a type the filter can compare against.
     *
     * @param  array<int, string>  $columns
     */
    protected static function booleanExpression(array $columns): string
    {
        $conditions = implode(' AND ', array_map(
            fn (string $column): string => "{$column} = 1",
            $columns,
        ));

        return "CASE WHEN {$conditions} THEN 1 ELSE 0 END";
    }
}
