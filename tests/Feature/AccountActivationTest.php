<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Customer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The is_active toggles write straight to the record without consulting a
 * policy, so the only thing standing between a super admin and locking every
 * full-access account out of the panel is the column's `disabled()` closure.
 * These tests drive `updateTableColumnState` directly, which is the same
 * server-side path the toggle hits, rather than asserting on the rendered UI.
 */
class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(bool $isActive = true): User
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create(['is_active' => $isActive]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function actAsPanelUser(User $user): void
    {
        Gate::before(fn () => true);
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
    }

    public function test_super_admin_can_deactivate_and_reactivate_another_admin(): void
    {
        $this->actAsPanelUser($this->superAdmin());

        Role::findOrCreate('ubap', 'web');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('ubap');

        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $other->getKey(), false);
        $this->assertFalse($other->fresh()->is_active);

        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $other->getKey(), true);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_a_super_admin_cannot_deactivate_their_own_account(): void
    {
        $self = $this->superAdmin();
        // A second active super admin, so self-lockout is the only guard in play.
        $this->superAdmin();
        $this->actAsPanelUser($self);

        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $self->getKey(), false);

        $this->assertTrue($self->fresh()->is_active);
    }

    public function test_the_last_active_super_admin_cannot_be_deactivated(): void
    {
        $actor = $this->superAdmin();
        $target = $this->superAdmin();
        $this->actAsPanelUser($actor);

        // Knocking out one of the two is fine...
        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $target->getKey(), false);
        $this->assertFalse($target->fresh()->is_active);

        // ...but that leaves $actor as the last one, so nobody may switch it off.
        $this->actAsPanelUser($target);
        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', $actor->getKey(), false);

        $this->assertTrue($actor->fresh()->is_active);
    }

    public function test_a_deactivated_admin_loses_panel_access(): void
    {
        $admin = $this->superAdmin(isActive: false);

        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_super_admin_can_toggle_a_customer(): void
    {
        $this->actAsPanelUser($this->superAdmin());

        $customer = Customer::factory()->create(['is_active' => true]);

        Livewire::test(ListCustomers::class)
            ->call('updateTableColumnState', 'is_active', $customer->getKey(), false);

        $this->assertFalse($customer->fresh()->is_active);
    }

    public function test_a_deactivated_customer_cannot_log_in(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make('secret-password'),
            'is_active' => false,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors(Fortify::username());
        $this->assertGuest('customer');
    }

    public function test_an_active_customer_can_still_log_in(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'secret-password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated('customer');
    }

    public function test_a_wrong_password_does_not_reveal_that_an_account_is_deactivated(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make('secret-password'),
            'is_active' => false,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'not-the-password',
        ]);

        $errors = session('errors')->getBag('default')->all();
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsStringIgnoringCase('deactivated', implode(' ', $errors));
        $this->assertGuest('customer');
    }
}
