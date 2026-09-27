<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The All/Active/Inactive/Trash tabs replaced the product stat widgets and
 * the table's is_active and Trashed filters, so they alone decide which
 * products the admin list shows.
 */
class ProductListTabsTest extends TestCase
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

    public function test_tabs_separate_active_inactive_and_deleted_products(): void
    {
        $active = Product::factory()->create(['is_active' => true, 'has_variants' => false]);
        $inactive = Product::factory()->create(['is_active' => false, 'has_variants' => false]);
        // Still flagged active: a deleted product must appear only under Trash.
        $deleted = Product::factory()->create(['is_active' => true, 'has_variants' => false]);
        $deleted->delete();
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$active, $inactive, $deleted])
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive, $deleted])
            ->set('activeTab', 'inactive')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active, $deleted])
            ->set('activeTab', 'trash')
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active, $inactive]);
    }

    public function test_variant_products_explain_their_blank_sku(): void
    {
        $simple = Product::factory()->create(['has_variants' => false, 'sku' => 'SIMPLE-001']);
        $variable = Product::factory()->create(['has_variants' => true, 'sku' => null]);
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$simple, $variable])
            ->assertSee('SIMPLE-001')
            ->assertSee('With variants');
    }
}
