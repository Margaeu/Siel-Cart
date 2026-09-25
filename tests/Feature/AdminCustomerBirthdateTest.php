<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An admin can edit a customer's birthdate from CustomerForm, but the same
 * 13-year minimum age CreateNewCustomer enforces at registration applies
 * here too -- see CustomerProfileDateOfBirthTest for the customer-facing
 * equivalent on the storefront profile page.
 */
class AdminCustomerBirthdateTest extends TestCase
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

    public function test_admin_can_update_a_customers_birthdate(): void
    {
        $customer = Customer::factory()->create(['date_of_birth' => '2000-01-15']);
        $this->actingAsAdmin();

        $newDob = now()->subYears(30)->startOfDay()->format('Y-m-d');

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->fillForm(['date_of_birth' => $newDob])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newDob, $customer->fresh()->date_of_birth);
    }

    public function test_admin_cannot_set_a_customers_birthdate_under_thirteen_years_old(): void
    {
        $customer = Customer::factory()->create(['date_of_birth' => '2000-01-15']);
        $this->actingAsAdmin();

        $tooYoung = now()->subYears(13)->addDay()->format('Y-m-d');

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->fillForm(['date_of_birth' => $tooYoung])
            ->call('save')
            ->assertHasFormErrors(['date_of_birth']);

        $this->assertSame('2000-01-15', $customer->fresh()->date_of_birth);
    }
}
