<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Admin Account
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => Hash::make('password'), // Change this in production
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Permanent System Categories
        $categories = [
            'Tshirts',
            'Mugs',
            'Accessories',
            'Stationary',
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );
        }
    }
}