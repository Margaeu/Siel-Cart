<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics'],
            ['name' => 'Fashion & Apparel'],
            ['name' => 'Home & Garden'],
            ['name' => 'Sports & Outdoors'],
            ['name' => 'Books & Media'],
            ['name' => 'Beauty & Personal Care'],
            ['name' => 'Toys & Games'],
            ['name' => 'Automotive'],
            ['name' => 'Health & Wellness'],
            ['name' => 'Pet Supplies'],
        ];

        foreach ($categories as $index => $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => \Illuminate\Support\Str::slug($category['name']),
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $this->command->info('Categories created successfully!');
    }
}
