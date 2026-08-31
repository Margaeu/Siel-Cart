<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        DB::table('categories')->delete();
        
        DB::table('categories')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Tshirts',
                'slug' => 'tshirts',
                'description' => NULL,
                'image' => 'categories/51701eba-e694-4118-9e31-473997fd2436-v1.jpg',
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => '2026-08-20 13:25:05',
                'updated_at' => '2026-08-20 13:40:02',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Mugs',
                'slug' => 'mugs',
                'description' => NULL,
                'image' => NULL,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => '2026-08-20 13:25:05',
                'updated_at' => '2026-08-20 13:25:05',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => NULL,
                'image' => NULL,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => '2026-08-20 13:25:05',
                'updated_at' => '2026-08-20 13:25:05',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Stationary',
                'slug' => 'stationary',
                'description' => NULL,
                'image' => NULL,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => '2026-08-20 13:25:05',
                'updated_at' => '2026-08-20 13:25:05',
            ),
        ));
        
        
    }
}