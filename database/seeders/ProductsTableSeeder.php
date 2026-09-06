<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Safe database clear preventing foreign key constraint failures
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $products = [
            [
                'id' => 1,
                'category_id' => 1,
                'name' => 'Baybayin T-Shirt v1',
                'slug' => 'permanent-system-product-1',
                'sku' => 'PERM-001',
                'short_description' => 'Colored tee featuring bold Baybayin script design',
                'description' => '<p>A classic colored shirt printed with the Sielesyuan design in Baybayin script, celebrating Filipino heritage and CLSU pride. Made from soft, breathable cotton fabric, perfect for everyday wear or campus events.</p>',
                'price' => 250.00,
                'stock_quantity' => 20,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_featured' => true,
                'has_variants' => true,
                'views_count' => 18,
            ],
            [
                'id' => 2,
                'category_id' => 2,
                'name' => 'Enamel CLSU Mugs',
                'slug' => 'permanent-system-product-2',
                'sku' => 'PERM-002',
                'short_description' => 'Enamel mug with CLSU branding',
                'description' => '<p>A classic enamel mug featuring the CLSU logo, combining durability with a nostalgic, vintage-inspired design. Great for coffee, tea, or camping trips.</p>',
                'price' => 250.00,
                'stock_quantity' => 10,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_featured' => true,
                'has_variants' => true,
                'views_count' => 3,
            ],
            [
                'id' => 3,
                'category_id' => 2,
                'name' => 'CLSU Tumbler',
                'slug' => 'clsu-tumbler',
                'sku' => 'PERM-003',
                'short_description' => 'Tumbler with CLSU branding',
                'description' => '<p>A durable tumbler featuring the CLSU logo, designed to keep beverages at the right temperature throughout the day. A practical and stylish accessory for campus life.</p>',
                'price' => 750.00,
                'stock_quantity' => 10,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_featured' => true,
                'has_variants' => true,
                'views_count' => 3,
            ],
            [
                'id' => 4,
                'category_id' => 1,
                'name' => 'Sielesyuan Tshirt',
                'slug' => 'permanent-system-product-4',
                'sku' => 'PERM-004',
                'short_description' => 'Classic Sielesyuan design tee',
                'description' => '<p>The original Sielesyuan shirt, featuring a design that reflects Filipino culture and CLSU identity. Comfortable and versatile, perfect for daily wear.</p>',
                'price' => 350.00,
                'stock_quantity' => 30,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_featured' => true,
                'has_variants' => true,
                'views_count' => 0,
            ],
            [
                'id' => 5,
                'category_id' => 1,
                'name' => 'CLSU Windbreaker',
                'slug' => 'permanent-system-product-5',
                'sku' => 'PERM-005',
                'short_description' => 'Lightweight windbreaker with CLSU print',
                'description' => '<p>A lightweight and functional windbreaker jacket featuring the CLSU design, perfect for outdoor activities, rainy days, or casual campus wear.</p>',
                'price' => 899.00,
                'stock_quantity' => 3,
                'low_stock_threshold' => 2,
                'is_active' => true,
                'is_featured' => true,
                'has_variants' => true,
                'views_count' => 1,
            ],
            [
                'id' => 6,
                'category_id' => 1,
                'name' => 'CLSU Athletes Hoodie',
                'slug' => 'permanent-system-product-6',
                'sku' => 'PERM-006',
                'short_description' => 'Cozy green hoodie with Cobra-themed print',
                'description' => '<p>A warm and comfortable hoodie in green, featuring the Cobra design that represents school spirit. Perfect for cool mornings, late-night studying, or showing your CLSU pride.</p>',
                'price' => 899.00,
                'stock_quantity' => 1,
                'low_stock_threshold' => 2,
                'is_active' => true,
                'is_featured' => false,
                'has_variants' => true,
                'views_count' => 12,
            ],
            [
                'id' => 7,
                'category_id' => 1,
                'name' => 'Glory and Honor Shirt',
                'slug' => 'glory-and-honor-shirt',
                'sku' => 'PERM-007',
                'short_description' => "Shirt inspired by CLSU's motto",
                'description' => '<p>A shirt inspired by the university\'s values, featuring the "Glory and Honor" print. A meaningful piece for students who take pride in their alma mater\'s legacy and achievements.</p>',
                'price' => 250.00,
                'stock_quantity' => 10,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_featured' => false,
                'has_variants' => true,
                'views_count' => 0,
            ],
            [
                'id' => 8,
                'category_id' => 4,
                'name' => 'CLSU Notebook',
                'slug' => 'clsu-notebook',
                'sku' => 'PERM-008',
                'short_description' => 'Notebook with CLSU cover design',
                'description' => '<p>A high-quality notebook featuring the CLSU design on its cover, perfect for note-taking, journaling, or sketching. A must-have for students who want functional yet school-spirited stationery.</p>',
                'price' => 80.00,
                'stock_quantity' => 20,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_featured' => false,
                'has_variants' => true,
                'views_count' => 0,
            ],
        ];

        $now = now();

        $formattedProducts = array_map(function ($product) use ($now) {
            return array_merge($product, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $products);

        Product::insert($formattedProducts);
    }
}