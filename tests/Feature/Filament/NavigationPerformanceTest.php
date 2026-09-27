<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    public static function imageLists(): array
    {
        return [
            'products' => [ListProducts::class],
            'categories' => [ListCategories::class],
            'banners' => [ListBanners::class],
        ];
    }

    #[DataProvider('imageLists')]
    public function test_image_lists_render_without_remote_existence_checks(string $page): void
    {
        $path = 'test/image.jpg';
        match ($page) {
            ListProducts::class => Product::factory()->create(['has_variants' => false])
                ->images()->create(['image_path' => $path, 'is_primary' => true]),
            ListCategories::class => Category::factory()->create(['image' => $path]),
            ListBanners::class => Banner::create(['image_path' => $path, 'is_active' => true]),
        };

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldNotReceive('exists');
        $disk->shouldNotReceive('fileExists');
        $disk->shouldReceive('temporaryUrl')->with($path, Mockery::any())
            ->atLeast()->once()->andReturn('https://media.example.com/test/image.jpg');
        Storage::shouldReceive('disk')->with('r2')->andReturn($disk);

        Livewire::test($page)
            ->assertOk()
            ->assertSeeHtml('https://media.example.com/test/image.jpg');
    }

    public function test_sidebar_links_use_spa_navigation(): void
    {
        $this->get('/admin/themes')
            ->assertOk()
            ->assertSeeHtml('wire:navigate');
    }
}
