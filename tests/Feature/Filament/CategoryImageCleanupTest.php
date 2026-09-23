<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Category images are removed from R2 in a DB::afterCommit() callback, so
 * these tests use DatabaseMigrations rather than RefreshDatabase: with no
 * wrapping test transaction, the delete really commits and the callback runs
 * exactly as it would in production.
 */
class CategoryImageCleanupTest extends TestCase
{
    use DatabaseMigrations;

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

    private function categoryWithImage(string $path, array $attributes = []): Category
    {
        Storage::disk('r2')->put($path, UploadedFile::fake()->image('category.jpg')->getContent());

        return Category::factory()->create(['image' => $path, ...$attributes]);
    }

    public function test_deleting_an_unassigned_category_removes_its_image(): void
    {
        $category = $this->categoryWithImage('categories/merch.jpg');
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        Storage::disk('r2')->assertMissing('categories/merch.jpg');
    }

    public function test_a_blocked_delete_keeps_the_image(): void
    {
        $category = $this->categoryWithImage('categories/merch.jpg', ['name' => 'Merch', 'slug' => 'merch']);
        Product::factory()->for($category)->create();
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('"Merch" can\'t be deleted');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        Storage::disk('r2')->assertExists('categories/merch.jpg');
    }

    public function test_a_blocked_bulk_delete_keeps_every_image(): void
    {
        $empty = $this->categoryWithImage('categories/empty.jpg');
        $assigned = $this->categoryWithImage('categories/assigned.jpg');
        Product::factory()->for($assigned)->create();
        $this->actingAsAdmin();

        Livewire::test(ListCategories::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$empty, $assigned]);

        Storage::disk('r2')->assertExists('categories/empty.jpg');
        Storage::disk('r2')->assertExists('categories/assigned.jpg');
    }

    public function test_a_bulk_delete_removes_each_image(): void
    {
        $first = $this->categoryWithImage('categories/first.jpg');
        $second = $this->categoryWithImage('categories/second.jpg');
        $this->actingAsAdmin();

        Livewire::test(ListCategories::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$first, $second]);

        Storage::disk('r2')->assertMissing('categories/first.jpg');
        Storage::disk('r2')->assertMissing('categories/second.jpg');
    }

    public function test_an_image_still_used_by_another_category_is_kept(): void
    {
        $deleted = $this->categoryWithImage('categories/shared.jpg');
        Category::factory()->create(['image' => 'categories/shared.jpg']);

        $deleted->delete();

        Storage::disk('r2')->assertExists('categories/shared.jpg');
    }
}
