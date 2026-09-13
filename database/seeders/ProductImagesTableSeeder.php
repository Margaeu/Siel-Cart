<?php

namespace Database\Seeders;

use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductImagesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_images')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $images = [
            // Product 1
            ['product_id' => 1, 'product_variant_id' => null, 'image_path' => 'products/01M0FRGB5W3F5PTQKJ1CGQQF9R.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],
            ['product_id' => 1, 'product_variant_id' => null, 'image_path' => 'products/01M0FRGCBFW4DK2NMSPVBP6ZQT.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 1],
            ['product_id' => 1, 'product_variant_id' => null, 'image_path' => 'products/01M0FRGDH7ZXCRAHFEFXBT6XK7.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 2],
            ['product_id' => 1, 'product_variant_id' => null, 'image_path' => 'products/01M0FRGEFK97QFAF3MZTXT6RNZ.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 3],

            // Product 2
            ['product_id' => 2, 'product_variant_id' => null, 'image_path' => 'products/01M0FSA0TWP16GAKFD5HZ0PG7A.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],
            ['product_id' => 2, 'product_variant_id' => null, 'image_path' => 'products/01M0FSA2248YKFX29N8NJVJYQR.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 1],

            // Product 3
            ['product_id' => 3, 'product_variant_id' => null, 'image_path' => 'products/01M0FVV8VEKED75DMA3X5KJVBP.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],
            ['product_id' => 3, 'product_variant_id' => null, 'image_path' => 'products/01M0FVV9PG7TEG1DEN6AWMNJSD.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 1],

            // Product 4
            ['product_id' => 4, 'product_variant_id' => null, 'image_path' => 'products/01M0FY9YZXXRV41NYEKVA6DGB9.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],

            // Product 5
            ['product_id' => 5, 'product_variant_id' => null, 'image_path' => 'products/01M0FY0MTZFJMP8JMPYNQ1MPZ8.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],

            // Product 6
            ['product_id' => 6, 'product_variant_id' => null, 'image_path' => 'products/01M0FYGFV44D232NFV14SMENDV.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],

            // Product 7
            ['product_id' => 7, 'product_variant_id' => null, 'image_path' => 'products/01M0FZM01EJQVRAH61XTSTFDZM.jpg', 'alt_text' => null, 'is_primary' => true, 'sort_order' => 0],
            ['product_id' => 7, 'product_variant_id' => null, 'image_path' => 'products/01M0FZM1N643APT1PHGP2WGX4P.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 1],
            ['product_id' => 7, 'product_variant_id' => null, 'image_path' => 'products/01M0FZM23RVMHCQS3BVEMGJY5R.jpg', 'alt_text' => null, 'is_primary' => false, 'sort_order' => 2],
        ];

        $now = now();

        $formattedImages = array_map(function ($image) use ($now) {
            return array_merge($image, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $images);

        ProductImage::insert($formattedImages);
    }
}