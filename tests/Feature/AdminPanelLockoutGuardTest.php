<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deactivating an admin is not the only way to strip their panel access —
 * deleting them and removing their super admin role do the same thing. All
 * three answer to User::canLosePanelAccessBy().
 *
 * These tests deliberately do NOT stub the gate: the delete guard lives in
 * UserPolicy, so a blanket `Gate::before(fn () => true)` would skip the very
 * code under test. Real permissions are granted instead.
 */
class AdminPanelLockoutGuardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(bool $isActive = true): User
    {
        $role = Role::findOrCreate('super_admin', 'web');

        foreach (['ViewAny:User', 'View:User', 'Update:User', 'Delete:User', 'DeleteAny:User'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create(['is_active' => $isActive]);
        $user->assignRole($role);

        return $user;
    }

    private function ordinaryAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate('ubap', 'web'));

        return $user;
    }

    private function actAsPanelUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    // --- Hole 1: deletion ---------------------------------------------------

    public function test_super_admin_can_delete_an_ordinary_admin(): void
    {
        $actor = $this->superAdmin();
        $this->superAdmin(); // keeps $actor from being the last one
        $target = $this->ordinaryAdmin();
        $this->actAsPanelUser($actor);

        $this->assertTrue(Gate::forUser($actor)->allows('delete', $target));

        // The row-level delete lives on the edit page's header, not the table.
        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('delete');

        $this->assertModelMissing($target);
    }

    public function test_a_super_admin_cannot_delete_their_own_account(): void
    {
        $actor = $this->superAdmin();
        $this->superAdmin(); // so self-lockout is the only guard in play
        $this->actAsPanelUser($actor);

        $response = Gate::forUser($actor)->inspect('delete', $actor);

        $this->assertTrue($response->denied());
        $this->assertSame('You cannot delete your own account.', $response->message());
    }

    public function test_the_last_active_super_admin_cannot_be_deleted(): void
    {
        $actor = $this->superAdmin();
        $target = $this->superAdmin();
        $this->actAsPanelUser($actor);

        // Two are active, so either may go...
        $this->assertTrue(Gate::forUser($actor)->allows('delete', $target));
        $target->delete();

        // ...but now $actor is the only one left.
        $other = $this->superAdmin();
        $this->actAsPanelUser($other);
        $other->update(['is_active' => false]);

        $response = Gate::forUser($other)->inspect('delete', $actor->fresh());

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('last active super admin', (string) $response->message());
    }

    public function test_bulk_delete_skips_protected_super_admins(): void
    {
        $actor = $this->superAdmin();
        $disposable = $this->ordinaryAdmin();
        $this->actAsPanelUser($actor);

        Livewire::test(ListUsers::class)
            ->selectTableRecords([$actor->getKey(), $disposable->getKey()])
            ->callAction(TestAction::make('delete')->table()->bulk());

        // The ordinary admin goes; the last active super admin is filtered out.
        $this->assertModelMissing($disposable);
        $this->assertModelExists($actor);
    }

    // --- Hole 2: role removal -----------------------------------------------

    public function test_removing_your_own_super_admin_role_is_rejected(): void
    {
        $actor = $this->superAdmin();
        $this->superAdmin(); // not the last one, so only self-lockout applies
        $this->actAsPanelUser($actor);

        Livewire::test(EditUser::class, ['record' => $actor->getKey()])
            ->fillForm($this->editFormData([Role::findOrCreate('ubap', 'web')->getKey()]))
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($actor->fresh()->hasRole('super_admin'));
    }

    /**
     * UserForm marks `password` as required on edit as well as create, so every
     * save has to carry one. That is a pre-existing quirk of the form, not
     * something these guards care about.
     *
     * @param  array<int, int|string>  $roleIds
     * @return array<string, mixed>
     */
    private function editFormData(array $roleIds): array
    {
        return ['roles' => $roleIds, 'password' => 'unchanged-password'];
    }

    public function test_removing_the_role_from_the_last_super_admin_is_rejected(): void
    {
        $actor = $this->superAdmin();
        $target = $this->superAdmin();
        $target->update(['is_active' => false]);
        $this->actAsPanelUser($target);

        Livewire::test(EditUser::class, ['record' => $actor->getKey()])
            ->fillForm($this->editFormData([]))
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($actor->fresh()->hasRole('super_admin'));
    }

    public function test_roles_can_still_be_changed_on_an_unprotected_admin(): void
    {
        $actor = $this->superAdmin();
        $this->superAdmin();
        $target = $this->ordinaryAdmin();
        $this->actAsPanelUser($actor);

        $stratcomm = Role::findOrCreate('stratcomm', 'web');

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm($this->editFormData([$stratcomm->getKey()]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('stratcomm'));
    }

    public function test_creating_a_user_is_unaffected_by_the_role_guard(): void
    {
        $actor = $this->superAdmin();
        $this->actAsPanelUser($actor);

        Role::findOrCreate('super_admin', 'web')
            ->givePermissionTo(Permission::findOrCreate('Create:User', 'web'));
        $actor->unsetRelation('roles')->unsetRelation('permissions');

        // The rule reads $record, which is null on create — it must not blow up.
        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => 'new.admin@example.test',
                'password' => 'a-password',
                'roles' => [Role::findOrCreate('ubap', 'web')->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'new.admin@example.test']);
    }

    public function test_a_super_admin_role_can_be_dropped_while_another_is_active(): void
    {
        $actor = $this->superAdmin();
        $target = $this->superAdmin();
        $this->actAsPanelUser($actor);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm($this->editFormData([Role::findOrCreate('ubap', 'web')->getKey()]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($target->fresh()->hasRole('super_admin'));
    }
}
