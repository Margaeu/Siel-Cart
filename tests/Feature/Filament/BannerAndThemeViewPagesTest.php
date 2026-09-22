<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Banners\Pages\ViewBanner;
use App\Filament\Resources\Themes\Pages\ViewTheme;
use App\Models\Banner;
use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BannerAndThemeViewPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    public function test_banner_view_page_shows_display_settings(): void
    {
        $banner = Banner::create(['image_path' => 'banners/test.jpg', 'is_active' => false, 'sort_order' => 7]);
        $this->actingAsAdmin();

        Livewire::test(ViewBanner::class, ['record' => $banner->getRouteKey()])
            ->assertOk()
            ->assertSee('Display settings')
            ->assertSee('Inactive')
            ->assertSee('7');
    }

    public function test_theme_view_page_shows_name_status_and_hex_codes(): void
    {
        $theme = Theme::create([
            'name' => 'Christmas Promo',
            'primary_color' => '#1E6031',
            'secondary_color' => '#E0A70D',
            'is_active' => true,
        ]);
        $this->actingAsAdmin();

        Livewire::test(ViewTheme::class, ['record' => $theme->getRouteKey()])
            ->assertOk()
            ->assertSee('Christmas Promo')
            ->assertSee('Active on storefront')
            ->assertSee('#1E6031')
            ->assertSee('#E0A70D');
    }
}
