<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermanentProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure categories exist before attaching products
        $categories = [
            'Tshirts' => Category::firstOrCreate(
                ['slug' => Str::slug('Tshirts')],
                ['name' => 'Tshirts']
            ),
            'Mugs' => Category::firstOrCreate(
                ['slug' => Str::slug('Mugs')],
                ['name' => 'Mugs']
            ),
            'Accessories' => Category::firstOrCreate(
                ['slug' => Str::slug('Accessories')],
                ['name' => 'Accessories']
            ),
            'Stationary' => Category::firstOrCreate(
                ['slug' => Str::slug('Stationary')],
                ['name' => 'Stationary']
            ),
        ];

        // 2. Define permanent products list across all 30 iterations
        $permanentProducts = [];

        for ($i = 1; $i <= 30; $i++) {
            $catKey = match (true) {
                $i <= 8 => 'Tshirts',
                $i <= 15 => 'Mugs',
                $i <= 22 => 'Accessories',
                default => 'Stationary', // Covers $i = 23 through 30 safely
            };

            $permanentProducts[] = [
                'name' => "Permanent System Product {$i}",
                'slug' => "permanent-system-product-{$i}",
                'sku' => sprintf('PERM-%03d', $i),
                'price' => 29.99 + ($i * 5),
                'stock_quantity' => 100,
                'category_id' => $categories[$catKey]->id,
                'is_active' => true,
                'is_featured' => $i <= 5, // Mark first 5 as featured
                'has_variants' => $i % 3 === 0, // Enable variants for every 3rd product
                'images' => [
                    "products/permanent/product-{$i}-1.jpg",
                    "products/permanent/product-{$i}-2.jpg",
                ],
            ];
        }

        // 3. Seed Products, Images, and Variants
        foreach ($permanentProducts as $productData) {
            $images = $productData['images'];
            unset($productData['images']);

            $product = Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            // Seed Permanent Images (Shared R2 paths)
            foreach ($images as $index => $imagePath) {
                $product->images()->updateOrCreate(
                    ['image_path' => $imagePath],
                    [
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]
                );
            }

            // Seed Permanent Variants if enabled
            if ($product->has_variants) {
                $variants = [
                    ['name' => 'Small', 'size' => 'S'],
                    ['name' => 'Medium', 'size' => 'M'],
                    ['name' => 'Large', 'size' => 'L'],
                ];

                foreach ($variants as $vIndex => $v) {
                    $product->variants()->updateOrCreate(
                        ['sku' => "{$product->sku}-{$v['size']}"],
                        [
                            'name' => "{$product->name} - {$v['name']}",
                            'options' => json_encode(['size' => $v['size']]),
                            'price' => $product->price,
                            'stock_quantity' => 50,
                            'is_active' => true,
                            'sort_order' => $vIndex,
                        ]
                    );
                }
            }
        }
    }
}