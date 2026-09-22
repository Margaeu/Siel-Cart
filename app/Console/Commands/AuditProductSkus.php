<?php

namespace App\Console\Commands;

use App\Support\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only preflight for the cross-table SKU rule (App\Rules\UniqueSku).
 *
 * Existing rows were saved before SKUs were trimmed or compared ignoring
 * case, so some may already collide under the new rule -- and the first admin
 * to edit one of them would be blocked from saving. This lists every such
 * conflict so a person can decide what each SKU should become.
 *
 * It only ever SELECTs. It never renames, clears, or deletes an identifier:
 * which of two colliding SKUs is "right" is a business decision.
 */
class AuditProductSkus extends Command
{
    protected $signature = 'products:audit-skus';

    protected $description = 'Report SKU collisions and blank identifiers across products and variants (read-only)';

    public function handle(): int
    {
        // Query builder, not Eloquent: soft-deleted products must be included
        // (the unique index and UniqueSku both count them), and no model
        // events or appended accessors are wanted for a report.
        $products = DB::table('products')
            ->select(['id', 'name', 'sku', 'has_variants', 'deleted_at'])
            ->orderBy('id')
            ->get();

        $variants = DB::table('product_variants')
            ->select(['id', 'product_id', 'name', 'sku'])
            ->orderBy('id')
            ->get();

        $productRows = $products
            ->filter(fn ($product) => Sku::comparisonKey($product->sku) !== null)
            ->map(fn ($product) => [
                'key' => Sku::comparisonKey($product->sku),
                'table' => 'product',
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name.($product->deleted_at ? ' (deleted)' : ''),
            ]);

        $variantRows = $variants
            ->filter(fn ($variant) => Sku::comparisonKey($variant->sku) !== null)
            ->map(fn ($variant) => [
                'key' => Sku::comparisonKey($variant->sku),
                'table' => 'variant',
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name.' (product #'.$variant->product_id.')',
            ]);

        $conflicts = 0;

        $conflicts += $this->reportGroups(
            'Product ↔ product SKU collisions',
            $this->duplicateGroups($productRows),
        );

        $conflicts += $this->reportGroups(
            'Variant ↔ variant SKU collisions',
            $this->duplicateGroups($variantRows),
        );

        $crossKeys = $productRows->pluck('key')->intersect($variantRows->pluck('key'))->unique();
        $conflicts += $this->reportGroups(
            'Product ↔ variant SKU collisions',
            $productRows->concat($variantRows)
                ->filter(fn (array $row) => $crossKeys->contains($row['key']))
                ->groupBy('key'),
        );

        $blank = $products
            ->filter(fn ($product) => ! $product->has_variants && Sku::comparisonKey($product->sku) === null)
            ->map(fn ($product) => ['product', $product->id, $this->display($product->sku), $product->name.($product->deleted_at ? ' (deleted)' : '')])
            ->concat($variants
                ->filter(fn ($variant) => Sku::comparisonKey($variant->sku) === null)
                ->map(fn ($variant) => ['variant', $variant->id, $this->display($variant->sku), $variant->name.' (product #'.$variant->product_id.')']))
            ->values();
        $conflicts += $this->reportRows('Blank identifiers (simple products and variants with no SKU)', $blank);

        $stale = $products
            ->filter(fn ($product) => $product->has_variants && Sku::comparisonKey($product->sku) !== null)
            ->map(fn ($product) => ['product', $product->id, $this->display($product->sku), $product->name.($product->deleted_at ? ' (deleted)' : '')])
            ->values();
        $conflicts += $this->reportRows('Variant products still carrying a product-level SKU', $stale);

        if ($conflicts === 0) {
            $this->info('No SKU conflicts found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("{$conflicts} issue(s) found. Nothing was changed; resolve them from the admin panel.");

        return self::FAILURE;
    }

    /**
     * @param  Collection<int, array{key: string}>  $rows
     */
    private function duplicateGroups(Collection $rows): Collection
    {
        return $rows->groupBy('key')->filter(fn (Collection $group) => $group->count() > 1);
    }

    /**
     * One issue per colliding comparison key.
     */
    private function reportGroups(string $title, Collection $groups): int
    {
        $rows = $groups
            ->flatMap(fn (Collection $group, string $key) => $group->map(fn (array $row) => [
                $key,
                $row['table'],
                $row['id'],
                $this->display($row['sku']),
                $row['name'],
            ]))
            ->values();

        $this->newLine();
        $this->line("<options=bold>{$title}</>");

        if ($rows->isEmpty()) {
            $this->line('  none');

            return 0;
        }

        $this->table(['Compared as', 'Table', 'ID', 'Stored SKU', 'Name'], $rows->all());

        return $groups->count();
    }

    private function reportRows(string $title, Collection $rows): int
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");

        if ($rows->isEmpty()) {
            $this->line('  none');

            return 0;
        }

        $this->table(['Table', 'ID', 'Stored SKU', 'Name'], $rows->all());

        return $rows->count();
    }

    /**
     * Quote the stored value so padding and emptiness are visible.
     */
    private function display(?string $sku): string
    {
        return $sku === null ? 'NULL' : '"'.$sku.'"';
    }
}
