<?php

namespace Tests\Feature\Filament\ActivityLogs;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Support\ActivityLogPresenter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roles sit in a pivot table, so granting or revoking one never dirties a
 * `users` column. These are privilege changes and must still be audited.
 */
class AdminRoleActivityTest extends TestCase
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

        // These tests are about what gets logged, not who may open the user
        // pages; the sqlite test database has no Shield permissions seeded.
        Gate::before(fn (): bool => true);
    }

    private function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    private function roleActivities(User $user)
    {
        return Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'Changed roles')
            ->orderBy('id')
            ->get();
    }

    public function test_granting_a_role_from_the_edit_page_is_logged_with_before_and_after(): void
    {
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');
        $staff->assignRole('ubap');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId('ubap'), $this->roleId('super_admin')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($staff->fresh()->hasRole('super_admin'));

        $activities = $this->roleActivities($staff);
        $this->assertCount(1, $activities);

        $activity = $activities->first();
        $this->assertSame('updated', $activity->event);
        $this->assertSame($this->superAdmin->id, $activity->causer_id);
        $this->assertSame(['ubap'], $activity->properties['old']['roles']);
        $this->assertSame(['super_admin', 'ubap'], $activity->properties['attributes']['roles']);

        $change = ActivityLogPresenter::for($activity)->changes()[0];
        $this->assertSame('Roles', $change['label']);
        $this->assertSame('ubap', $change['old']['full']);
        $this->assertSame('super_admin, ubap', $change['new']['full']);
    }

    public function test_revoking_every_role_is_logged(): void
    {
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');
        $staff->assignRole('stratcom');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity = $this->roleActivities($staff)->sole();
        $this->assertSame(['stratcom'], $activity->properties['old']['roles']);
        $this->assertSame([], $activity->properties['attributes']['roles']);
    }

    public function test_saving_without_touching_roles_writes_no_role_row(): void
    {
        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');
        $staff->assignRole('ubap');

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['first_name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(0, $this->roleActivities($staff));
    }

    public function test_the_roles_given_at_creation_are_logged(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'New',
                'last_name' => 'Staff',
                'email' => 'new.staff@example.com',
                'password' => 'password123',
                'roles' => [$this->roleId('ubap')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'new.staff@example.com')->sole();

        $activity = $this->roleActivities($created)->sole();
        $this->assertSame([], $activity->properties['old']['roles']);
        $this->assertSame(['ubap'], $activity->properties['attributes']['roles']);
    }
}
