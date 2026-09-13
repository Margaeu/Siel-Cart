<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermanentProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Reset tables cleanly to prevent duplicate SKUs and orphaned IDs
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_variants')->truncate();
        DB::table('product_images')->truncate();
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Resolve categories matching CategoriesTableSeeder
        $categories = [
            'Athletics' => Category::firstOrCreate(
                ['slug' => 'Athletics'],
                [
                    'name' => 'Athletics',
                    'image' => 'categories/51701eba-e694-4118-9e31-473997fd2436-v1.jpg',
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            ),
            'Merch' => Category::firstOrCreate(
                ['slug' => 'Merch'],
                [
                    'name' => 'Merch',
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            ),
            'Gift Set' => Category::firstOrCreate(
                ['slug' => 'gift-set'],
                [
                    'name' => 'Gift Set',
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            ),
        ];

        // 3. Define permanent products
        $permanentProducts = [];

        for ($i = 1; $i <= 30; $i++) {
            $catKey = match (true) {
                $i <= 8 => 'Athletics',
                $i <= 15 => 'Merch',
                default => 'Gift Set',
            };

            $permanentProducts[] = [
                'name' => "Permanent System Product {$i}",
                'slug' => "permanent-system-product-{$i}",
                'sku' => sprintf('PERM-%03d', $i),
                'price' => 29.99 + ($i * 5),
                'stock_quantity' => 100,
                'category_id' => $categories[$catKey]->id,
                'is_active' => true,
                'is_featured' => $i <= 5,
                'has_variants' => in_array($i, [1, 2, 3, 4, 5, 6, 7]), // Handled by ProductVariantsTableSeeder
                'images' => [
                    "products/permanent/product-{$i}-1.jpg",
                    "products/permanent/product-{$i}-2.jpg",
                ],
            ];
        }

        // 4. Seed Products and Primary Images
        foreach ($permanentProducts as $productData) {
            $images = $productData['images'];
            unset($productData['images']);

            $product = Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            foreach ($images as $index => $imagePath) {
                $product->images()->updateOrCreate(
                    ['image_path' => $imagePath],
                    [
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]
                );
            }
        }
    }
}