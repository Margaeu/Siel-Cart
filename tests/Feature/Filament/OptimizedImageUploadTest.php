<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `OptimizedImageStorage::store()` is wired into every admin image upload via
 * `saveUploadedFileUsing()` (see CategoryForm, BannerForm, ProductForm), but
 * nothing else in the suite drives a real `UploadedFile` through a Filament
 * form -- the other CRUD tests set already-stored path strings directly,
 * which bypasses that hook entirely. This exercises the real upload path
 * once, through the category form, to confirm the hook actually fires and
 * produces a re-encoded, capped-width file rather than a byte-for-byte copy.
 */
class OptimizedImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
    }

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

    public function test_an_oversized_category_image_is_downscaled_and_reencoded_on_upload(): void
    {
        $this->actingAsAdmin();

        // Simulates a straight-from-camera photo, well past the 800px cap
        // CategoryForm sets for OptimizedImageStorage::store().
        $original = UploadedFile::fake()->image('storefront.jpg', 3000, 2000);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Merch',
                'is_active' => true,
                'sort_order' => 0,
                'image' => $original,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // CategoryForm's FileUpload uses preserveFilenames(), so the stored
        // path keeps the original client filename under categories/.
        Storage::disk('r2')->assertExists('categories/storefront.jpg');

        $stored = ImageManager::gd()->read(
            Storage::disk('r2')->get('categories/storefront.jpg'),
        );

        $this->assertSame(800, $stored->width());
        $this->assertLessThan(3000 * 2000, $stored->width() * $stored->height());
    }
}
