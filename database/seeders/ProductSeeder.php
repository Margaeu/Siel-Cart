<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();
        $totalProducts = 30; // Adjusted for 30 products

        $this->command->info("Creating {$totalProducts} products with R2 images...");
        $bar = $this->command->getOutput()->createProgressBar($totalProducts);

        for ($i = 1; $i <= $totalProducts; $i++) {
            $product = Product::factory()->create([
                'category_id' => $categories->random()->id,
            ]);

            // Assigns images specifically matching product number (e.g., product-1-1.jpg, product-1-2.jpg)
            $imageCount = rand(2, 4); // 2 to 4 images per product

            for ($j = 1; $j <= $imageCount; $j++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => "products/product-{$i}-{$j}.jpg",
                    'is_primary' => $j === 1,
                    'sort_order' => $j - 1,
                ]);
            }

            // Create variants for products with variants enabled
            if ($product->has_variants) {
                $colors = ['Red', 'Blue', 'Black', 'White'];
                $sizes = ['S', 'M', 'L'];

                foreach ($colors as $colorIndex => $color) {
                    foreach ($sizes as $sizeIndex => $size) {
                        if (rand(0, 100) > 50) {
                            ProductVariant::factory()->create([
                                'product_id' => $product->id,
                                'name' => "{$color} - {$size}",
                                'price' => $product->price + rand(0, 20),
                                'sort_order' => ($colorIndex * count($sizes)) + $sizeIndex,
                            ]);
                        }
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('30 Products seeded successfully with custom R2 image paths!');
    }
}