<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SystemSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Admin Account. is_active + super_admin are both
        // required for User::canAccessPanel() — without either, this account
        // can log in to Fortify's `web` guard but gets bounced from /admin.
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => Hash::make('password'), // Change this in production
                'is_active' => true,
                // 'email_verified_at' => now(),
            ]
        );

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        if (! $admin->hasRole($superAdminRole)) {
            $admin->assignRole($superAdminRole);
        }

        // 2. Create Permanent System Categories
        $categories = [
            'Merchandise',
            'Athletics',
            'Gift Set',
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );
        }

        // 3. Create the default active Color Theme, matching the site's
        // original hardcoded look. Manage from Admin → Design → Color
        // Themes & Fonts.
        Theme::firstOrCreate(
            ['name' => 'Default Green'],
            [
                'primary_color' => '#557F13',
                'secondary_color' => '#FFD801',
                'is_active' => true,
            ]
        );
    }
}
