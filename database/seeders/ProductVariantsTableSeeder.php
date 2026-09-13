<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductVariantsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clear existing variants before repopulating
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_variants')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Map variants directly to parent Product SKUs
        $variantGroups = [
            'PERM-001' => [
                ['sku' => 'VAR-XKDSEPAN', 'name' => 'Tatak CLSU', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'VAR-YESGLDSD', 'name' => 'Brown Baybayin', 'price' => 250.00, 'stock_quantity' => 4, 'is_active' => true, 'sort_order' => 1],
                ['sku' => 'VAR-EWZ20SUG', 'name' => 'Black Baybayin', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 2],
                ['sku' => 'VAR-E56A3NYO', 'name' => 'Brown and Black Baybayin', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 3],
            ],
            'PERM-002' => [
                ['sku' => 'PERM-003-S', 'name' => 'CLSU Tumbler Green', 'price' => 44.99, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'PERM-003-M', 'name' => 'CLSU Tumbler Yellow', 'price' => 750.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 1],
            ],
            'PERM-003' => [
                ['sku' => 'VAR-CPQHQPTJ', 'name' => 'Black', 'price' => 350.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'VAR-ROQMVF9N', 'name' => 'White', 'price' => 350.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 1],
            ],
            'PERM-004' => [
                ['sku' => 'VAR-OSRLJ9XS', 'name' => 'CLSU Windbreaker - Large', 'price' => 899.00, 'stock_quantity' => 1, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'VAR-ABBLKWJA', 'name' => 'CLSU Windbreaker - XLarge', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 1],
            ],
            'PERM-005' => [
                ['sku' => 'PERM-006-S', 'name' => 'CLSU Athletes Hoodie - Small', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'PERM-006-M', 'name' => 'CLSU Athletes Hoodie - Medium', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 1],
                ['sku' => 'PERM-006-L', 'name' => 'CLSU Athletes Hoodie - Large', 'price' => 899.00, 'stock_quantity' => 1, 'is_active' => true, 'sort_order' => 2],
            ],
            'PERM-006' => [
                ['sku' => 'VAR-KB0C8RN0', 'name' => 'Honor and Glory - Small', 'price' => 250.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'VAR-A8RLNI9E', 'name' => 'Honor and Glory - Medium', 'price' => 250.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 1],
                ['sku' => 'VAR-NDLHXOGR', 'name' => 'Honor and Glory - Large', 'price' => 250.00, 'stock_quantity' => 10, 'is_active' => true, 'sort_order' => 2],
            ],
            'PERM-007' => [
                ['sku' => 'VAR-RLNR5RSC', 'name' => 'CLSU Notebook - Yellow', 'price' => 80.00, 'stock_quantity' => 3, 'is_active' => true, 'sort_order' => 0],
                ['sku' => 'VAR-FSETNRBJ', 'name' => 'CLSU Notebook - Gray', 'price' => 80.00, 'stock_quantity' => 3, 'is_active' => true, 'sort_order' => 1],
                ['sku' => 'VAR-87HASTRC', 'name' => 'CLSU Notebook - Green', 'price' => 80.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 2],
            ],
        ];

        // 3. Populate variants
        foreach ($variantGroups as $parentSku => $variants) {
            $product = Product::where('sku', $parentSku)->first();

            if (! $product) {
                continue;
            }

            if (! $product->has_variants) {
                $product->update(['has_variants' => true]);
            }

            foreach ($variants as $variantData) {
                ProductVariant::updateOrCreate(
                    ['sku' => $variantData['sku']],
                    array_merge($variantData, ['product_id' => $product->id])
                );
            }
        }
    }
}