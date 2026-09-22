<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The All/Active/Inactive tabs replaced the table's is_active filter, so they
 * alone decide which categories the admin list shows.
 */
class CategoryListTabsTest extends TestCase
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
        // User::canAccessPanel() needs one of the panel roles.
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        // Authorization is not what these tests are about.
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    public function test_tabs_separate_active_and_inactive_categories(): void
    {
        $active = Category::factory()->create(['is_active' => true]);
        $inactive = Category::factory()->create(['is_active' => false]);
        $this->actingAsAdmin();

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$active, $inactive])
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->set('activeTab', 'inactive')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_the_products_column_shows_trashed_products_separately(): void
    {
        $withTrash = Category::factory()->create();
        Product::factory()->for($withTrash)->create();
        Product::factory()->for($withTrash)->create()->delete();
        $onlyTrash = Category::factory()->create();
        Product::factory()->for($onlyTrash)->create()->delete();
        $clean = Category::factory()->create();
        Product::factory()->for($clean)->create();
        $this->actingAsAdmin();

        // A category holding only trashed products used to read "0" and then
        // refuse to delete.
        Livewire::test(ListCategories::class)
            ->assertTableColumnFormattedStateSet('products_count', '1 (+1 in trash)', $withTrash)
            ->assertTableColumnFormattedStateSet('products_count', '0 (+1 in trash)', $onlyTrash)
            ->assertTableColumnFormattedStateSet('products_count', '1', $clean);
    }
}
