<?php

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Filament\ActivityLogs\ActivityLogResourceTest;
use Tests\TestCase;

/**
 * Deleting administrators must never lock the panel: no deleting yourself, and
 * at least one active super admin always survives. In production Shield lets
 * super_admin past every policy check, which is why the guard lives on the
 * actions and not in UserPolicy; here every role simply holds Delete:User, so
 * the policy allows everything and only the guard can refuse.
 */
class AdminDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny:User', 'View:User', 'Update:User', 'Delete:User'];

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Real Shield permissions rather than a Gate::before: every role may
        // delete administrators, so the only thing that can refuse is the guard.
        foreach (['super_admin', 'ubap', 'stratcom'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo(self::PERMISSIONS);
        }

        $this->superAdmin = $this->admin('super_admin', 'Super');
    }

    private function admin(string $role, string $firstName = 'Test', bool $active = true): User
    {
        $user = ActivityLogResourceTest::makeAdmin($firstName);
        $user->forceFill(['is_active' => $active])->save();
        $user->assignRole($role);

        return $user;
    }

    public function test_an_ordinary_administrator_can_still_be_deleted(): void
    {
        $ubap = $this->admin('ubap');

        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $ubap->getRouteKey()])
            ->assertActionVisible(DeleteAction::class)
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($ubap);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $this->admin('super_admin', 'Other');

        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->superAdmin->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        $this->assertModelExists($this->superAdmin);
    }

    public function test_the_last_active_super_admin_is_protected_from_another_admin(): void
    {
        // An inactive super admin does not count as a survivor.
        $this->admin('super_admin', 'Dormant', active: false);
        // Someone other than the target, so the self-delete rule isn't what
        // blocks it.
        $actor = $this->admin('ubap', 'Actor');

        $this->assertFalse($this->superAdmin->canLosePanelAccessBy($actor));
        $this->assertSame(
            'This is the last active super admin — you cannot delete it until another super admin is active.',
            $this->superAdmin->panelAccessLossBlockedReason($actor, 'delete'),
        );
        $this->assertNotNull(User::selectionAccessLossBlockedReason([$this->superAdmin], $actor, 'delete'));
    }

    public function test_the_delete_action_is_hidden_on_the_last_active_super_admin(): void
    {
        // The signed-in admin is a deactivated super admin, so the record
        // being edited is the only active one -- and not their own account.
        $last = $this->admin('super_admin', 'Last');
        $this->superAdmin->forceFill(['is_active' => false])->save();

        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $last->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        $this->assertModelExists($last);
    }

    public function test_a_super_admin_can_be_deleted_while_another_active_one_remains(): void
    {
        $other = $this->admin('super_admin', 'Other');

        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
            ->assertActionVisible(DeleteAction::class)
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($other);
    }

    public function test_bulk_delete_refuses_a_selection_containing_the_admins_own_account(): void
    {
        $ubap = $this->admin('ubap');
        $this->admin('super_admin', 'Other');

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$ubap, $this->superAdmin])
            ->assertNotified('No administrators were deleted');

        // All or nothing: the eligible account in the selection survives too.
        $this->assertModelExists($ubap);
        $this->assertModelExists($this->superAdmin);
    }

    public function test_each_super_admin_alone_is_deletable_but_not_all_of_them_together(): void
    {
        $actor = $this->admin('ubap', 'Actor');
        $second = $this->admin('super_admin', 'Second');

        $this->assertNull($this->superAdmin->panelAccessLossBlockedReason($actor, 'delete'));
        $this->assertNull($second->panelAccessLossBlockedReason($actor, 'delete'));

        $this->assertSame(
            'This selection includes every remaining active super admin — you cannot delete all of them. Keep at least one active super admin.',
            User::selectionAccessLossBlockedReason([$this->superAdmin, $second], $actor, 'delete'),
        );
    }

    public function test_bulk_delete_through_the_table_refuses_every_remaining_active_super_admin(): void
    {
        $second = $this->admin('super_admin', 'Second');
        $third = $this->admin('super_admin', 'Third');

        // The signed-in super admin is deactivated, so the two selected
        // accounts are the only active super admins. Neither is "the last" on
        // its own; together they are.
        $this->superAdmin->forceFill(['is_active' => false])->save();
        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$second, $third])
            ->assertNotified('No administrators were deleted');

        $this->assertModelExists($second);
        $this->assertModelExists($third);
    }

    public function test_bulk_delete_works_when_an_active_super_admin_survives(): void
    {
        $ubap = $this->admin('ubap');
        $stratcom = $this->admin('stratcom');
        $other = $this->admin('super_admin', 'Other');

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$ubap, $stratcom, $other]);

        $this->assertModelMissing($ubap);
        $this->assertModelMissing($stratcom);
        $this->assertModelMissing($other);
        $this->assertModelExists($this->superAdmin);
    }
}
