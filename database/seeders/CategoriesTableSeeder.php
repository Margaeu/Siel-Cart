<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
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
        DB::table('categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = [
            [
                'name' => 'Tshirts',
                'slug' => 'tshirts',
                'description' => null,
                'image' => 'categories/51701eba-e694-4118-9e31-473997fd2436-v1.jpg',
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Mugs',
                'slug' => 'mugs',
                'description' => null,
                'image' => null,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => null,
                'image' => null,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Stationary',
                'slug' => 'stationary',
                'description' => null,
                'image' => null,
                'is_active' => true,
                'sort_order' => 0,
            ],
        ];

        $now = now();

        $formattedCategories = array_map(function ($category) use ($now) {
            return array_merge($category, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $categories);

        Category::insert($formattedCategories);
    }
}