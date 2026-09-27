<?php

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Customer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Filament\ActivityLogs\ActivityLogResourceTest;
use Tests\TestCase;

/**
 * The unique index on `users.email` used to be the only guard, so a duplicate
 * address reached the database and failed as a SQL integrity error instead
 * of a form message.
 */
class UserEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (['super_admin', 'ubap', 'stratcom'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->superAdmin = ActivityLogResourceTest::makeAdmin('Super', 'Admin');
        $this->superAdmin->assignRole('super_admin');
        $this->actingAs($this->superAdmin);

        Gate::before(fn (): bool => true);
    }

    public function test_creating_an_admin_with_an_existing_email_is_rejected(): void
    {
        $existing = ActivityLogResourceTest::makeAdmin('Staff', 'Member');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Another',
                'last_name' => 'Person',
                'email' => $existing->email,
                'password' => 'password123',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_editing_an_admin_to_another_admins_email_is_rejected(): void
    {
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['email' => $this->superAdmin->email])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_creating_an_admin_with_a_customers_email_is_rejected(): void
    {
        $customer = Customer::factory()->create();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Another',
                'last_name' => 'Person',
                'email' => $customer->email,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertFalse(User::where('email', $customer->email)->exists());
    }

    public function test_editing_an_admin_to_a_customers_email_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['email' => $customer->email])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertNotSame($customer->email, $staff->fresh()->email);
    }

    public function test_saving_an_admin_with_their_own_email_is_allowed(): void
    {
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['first_name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $staff->fresh()->first_name);
    }
}
