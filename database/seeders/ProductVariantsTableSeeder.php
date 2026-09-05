<?php

namespace Database\Seeders;

use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductVariantsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Disable foreign key checks during cleanup to avoid relational errors
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_variants')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $variants = [
            // Product ID: 1
            ['product_id' => 1, 'sku' => 'VAR-UMSDFQVV', 'name' => 'Black', 'price' => 250.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 1, 'sku' => 'VAR-YZARQV0F', 'name' => 'Brown', 'price' => 250.00, 'stock_quantity' => 3, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 1, 'sku' => 'VAR-JUHLINGF', 'name' => 'Olive Green', 'price' => 250.00, 'stock_quantity' => 4, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 1, 'sku' => 'VAR-AUA0ZUGY', 'name' => 'White', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 0],

            // Product ID: 2
            ['product_id' => 2, 'sku' => 'VAR-XKDSEPAN', 'name' => 'Tatak CLSU', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 2, 'sku' => 'VAR-YESGLDSD', 'name' => 'Brown Baybayin', 'price' => 250.00, 'stock_quantity' => 4, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 2, 'sku' => 'VAR-EWZ20SUG', 'name' => 'Black Baybayin', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 2, 'sku' => 'VAR-E56A3NYO', 'name' => 'Brown and Black Baybayin', 'price' => 250.00, 'stock_quantity' => 2, 'is_active' => true, 'sort_order' => 0],

            // Product ID: 3
            ['product_id' => 3, 'sku' => 'PERM-003-S', 'name' => 'CLSU Tumbler Green', 'price' => 44.99, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 3, 'sku' => 'PERM-003-M', 'name' => 'CLSU Tumbler Yellow', 'price' => 750.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 1],

            // Product ID: 4
            ['product_id' => 4, 'sku' => 'VAR-CPQHQPTJ', 'name' => 'Black', 'price' => 350.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 4, 'sku' => 'VAR-ROQMVF9N', 'name' => 'White', 'price' => 350.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],

            // Product ID: 5
            ['product_id' => 5, 'sku' => 'VAR-OSRLJ9XS', 'name' => 'CLSU Windbreaker - Large', 'price' => 899.00, 'stock_quantity' => 1, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 5, 'sku' => 'VAR-ABBLKWJA', 'name' => 'CLSU Windbreaker - XLarge', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],

            // Product ID: 6
            ['product_id' => 6, 'sku' => 'PERM-006-S', 'name' => 'CLSU Athletes Hoodie - Small', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 6, 'sku' => 'PERM-006-M', 'name' => 'CLSU Athletes Hoodie - Medium', 'price' => 899.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 1],
            ['product_id' => 6, 'sku' => 'PERM-006-L', 'name' => 'CLSU Athletes Hoodie - Large', 'price' => 899.00, 'stock_quantity' => 1, 'is_active' => true, 'sort_order' => 2],

            // Product ID: 7
            ['product_id' => 7, 'sku' => 'VAR-KB0C8RN0', 'name' => 'Honor and Glory - Small', 'price' => 250.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 7, 'sku' => 'VAR-A8RLNI9E', 'name' => 'Honor and Glory - Medium', 'price' => 250.00, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 7, 'sku' => 'VAR-NDLHXOGR', 'name' => 'Honor and Glory - Large', 'price' => 250.00, 'stock_quantity' => 10, 'is_active' => true, 'sort_order' => 0],

            // Product ID: 8
            ['product_id' => 8, 'sku' => 'VAR-RLNR5RSC', 'name' => 'CLSU Notebook - Yellow', 'price' => 80.00, 'stock_quantity' => 3, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 8, 'sku' => 'VAR-FSETNRBJ', 'name' => 'CLSU Notebook - Gray', 'price' => 80.00, 'stock_quantity' => 3, 'is_active' => true, 'sort_order' => 0],
            ['product_id' => 8, 'sku' => 'VAR-87HASTRC', 'name' => 'CLSU Notebook - Green', 'price' => 80.00, 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 0],
        ];

        $now = now();

        $formattedVariants = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $variants);

        ProductVariant::insert($formattedVariants);
    }
}