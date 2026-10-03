<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Themes\Pages\EditTheme;
use App\Filament\Resources\Themes\Pages\ListThemes;
use App\Models\Banner;
use App\Models\Theme;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * Delete confirmations name what is affected and what can't be undone, and the
 * active theme can't be deleted out from under the storefront.
 */
class DestructiveActionConfirmationTest extends TestCase
{
    use BuildsResolvableOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function theme(string $name, bool $active): Theme
    {
        return Theme::create([
            'name' => $name,
            'primary_color' => '#1E6031',
            'secondary_color' => '#E0A70D',
            'is_active' => $active,
        ]);
    }

    public function test_the_active_theme_cannot_be_deleted_from_its_edit_page(): void
    {
        $active = $this->theme('Default Green', true);

        Livewire::test(EditTheme::class, ['record' => $active->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        $this->assertModelExists($active);
    }

    public function test_an_inactive_theme_can_still_be_deleted_and_the_modal_names_it(): void
    {
        $this->theme('Default Green', true);
        $promo = $this->theme('Christmas Promo', false);

        Livewire::test(EditTheme::class, ['record' => $promo->getRouteKey()])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalSee('Delete the "Christmas Promo" theme?')
            ->callMountedAction();

        $this->assertModelMissing($promo);
    }

    public function test_bulk_delete_refuses_a_selection_that_includes_the_active_theme(): void
    {
        $active = $this->theme('Default Green', true);
        $promo = $this->theme('Christmas Promo', false);

        Livewire::test(ListThemes::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$active, $promo])
            ->assertNotified('No themes were deleted');

        $this->assertModelExists($active);
        $this->assertModelExists($promo);
    }

    public function test_bulk_delete_of_inactive_themes_still_works(): void
    {
        $active = $this->theme('Default Green', true);
        $promo = $this->theme('Christmas Promo', false);
        $sale = $this->theme('Summer Sale', false);

        Livewire::test(ListThemes::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$promo, $sale]);

        $this->assertModelMissing($promo);
        $this->assertModelMissing($sale);
        $this->assertModelExists($active);
    }

    public function test_banner_delete_warns_that_it_is_permanent(): void
    {
        $banner = Banner::create(['image_path' => 'banners/test.jpg', 'is_active' => true, 'sort_order' => 1]);

        Livewire::test(EditBanner::class, ['record' => $banner->getRouteKey()])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalSee('cannot be restored');
    }

    public function test_order_delete_says_it_does_not_cancel_or_restock(): void
    {
        $order = $this->completedOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalSee("Delete order {$order->order_number}?")
            ->assertMountedActionModalSee('not returned to stock');
    }

    public function test_order_force_delete_says_it_cannot_be_undone(): void
    {
        $order = $this->completedOrder();
        $order->delete();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction(ForceDeleteAction::class)
            ->assertMountedActionModalSee("Permanently delete order {$order->order_number}?")
            ->assertMountedActionModalSee('cannot be undone');
    }
}
