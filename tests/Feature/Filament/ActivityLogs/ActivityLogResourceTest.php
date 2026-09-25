<?php

namespace Tests\Feature\Filament\ActivityLogs;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Widgets\ActivityLogStats;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Activity Logs resource is an audit trail: only super admins may read
 * it, and nobody may change it from the panel.
 *
 * Authorization here is deliberately NOT stubbed with a blanket
 * `Gate::before(fn () => true)` except where a test is proving that even an
 * all-permissive gate cannot unlock a mutation.
 */
class ActivityLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT = 'App\\Models\\Product';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-19 10:00:00', 'Asia/Manila'));
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Built by hand rather than with UserFactory: the factory still sets
     * email_verified_at, which the users table no longer has.
     */
    public static function makeAdmin(string $firstName = 'Test', string $lastName = 'Admin'): User
    {
        $user = new User;
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Str::lower($firstName.'.'.$lastName).'.'.Str::random(6).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function adminWithRole(string $role): User
    {
        $user = self::makeAdmin(Str::ucfirst($role));
        $user->assignRole(Role::findOrCreate($role, 'web'));

        // Creating the user is itself logged by the User model; start each
        // test from an empty trail so counts and orderings are exact.
        Activity::query()->delete();

        return $user;
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activity(array $attributes = []): Activity
    {
        $activity = new Activity;
        $activity->forceFill(array_merge([
            'log_name' => 'default',
            'description' => 'updated',
            'event' => 'updated',
            'properties' => [],
        ], $attributes));
        $activity->save();

        return $activity;
    }

    // --- Model and routes ---------------------------------------------------

    public function test_the_resource_uses_spaties_activity_model(): void
    {
        $this->assertSame(Activity::class, ActivityLogResource::getModel());
        $this->assertFalse(class_exists('App\\Models\\ActivityLog'));
        $this->assertSame('description', ActivityLogResource::getRecordTitleAttribute());
    }

    public function test_only_the_index_and_view_routes_are_registered(): void
    {
        $this->assertSame(['index', 'view'], array_keys(ActivityLogResource::getPages()));

        $this->assertTrue(Route::has('filament.admin.resources.admin-activity-logs.index'));
        $this->assertTrue(Route::has('filament.admin.resources.admin-activity-logs.view'));
        $this->assertFalse(Route::has('filament.admin.resources.admin-activity-logs.create'));
        $this->assertFalse(Route::has('filament.admin.resources.admin-activity-logs.edit'));
    }

    // --- Access -------------------------------------------------------------

    public function test_a_super_admin_can_open_the_index_and_view_pages(): void
    {
        $this->withoutVite();
        $admin = $this->adminWithRole('super_admin');
        $activity = $this->activity(['subject_type' => self::PRODUCT, 'subject_id' => 42]);

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Activity Logs');

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('view', ['record' => $activity]))
            ->assertOk()
            ->assertSee('Product #42');

        $this->actAs($admin);
        $this->assertTrue(ActivityLogResource::shouldRegisterNavigation());
    }

    /**
     * One role per test: switching users between HTTP requests inside a single
     * test trips AuthenticateSession, which is a harness artefact, not a
     * finding about this resource.
     *
     * @return array<string, array{string}>
     */
    public static function otherPanelRoles(): array
    {
        return [
            'ubap' => ['ubap'],
            'stratcom' => ['stratcom'],
        ];
    }

    #[DataProvider('otherPanelRoles')]
    public function test_other_admin_roles_cannot_discover_or_open_the_activity_log(string $role): void
    {
        $admin = $this->adminWithRole($role);
        $activity = $this->activity();

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('view', ['record' => $activity]))
            ->assertForbidden();

        $this->actAs($admin);
        $this->assertFalse(ActivityLogResource::canViewAny());
        $this->assertFalse(ActivityLogResource::shouldRegisterNavigation());
    }

    public function test_a_permission_granting_gate_still_cannot_unlock_viewing_for_non_super_admins(): void
    {
        Gate::before(fn () => true);
        $this->actAs($this->adminWithRole('ubap'));

        $this->assertFalse(ActivityLogResource::canViewAny());
        $this->assertFalse(ActivityLogResource::canView($this->activity()));
    }

    // --- Immutability -------------------------------------------------------

    public function test_every_mutating_ability_is_refused_even_for_a_super_admin_with_every_permission(): void
    {
        Gate::before(fn () => true);
        $this->actAs($this->adminWithRole('super_admin'));
        $activity = $this->activity();

        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($activity));
        $this->assertFalse(ActivityLogResource::canDelete($activity));
        $this->assertFalse(ActivityLogResource::canDeleteAny());
        $this->assertFalse(ActivityLogResource::canRestore($activity));
        $this->assertFalse(ActivityLogResource::canRestoreAny());
        $this->assertFalse(ActivityLogResource::canForceDelete($activity));
        $this->assertFalse(ActivityLogResource::canForceDeleteAny());
        $this->assertFalse(ActivityLogResource::canReplicate($activity));

        foreach (['create', 'update', 'delete', 'deleteAny', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse(ActivityLogResource::getAuthorizationResponse($ability, $activity)->allowed(), $ability);
        }
    }

    public function test_no_mutation_actions_are_offered_on_the_list_or_view_page(): void
    {
        $this->actAs($this->adminWithRole('super_admin'));
        $activity = $this->activity();

        Livewire::test(ListActivityLogs::class)
            ->assertOk()
            ->assertActionDoesNotExist('create')
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('restore')
            ->assertTableActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('restore');

        Livewire::test(ViewActivityLog::class, ['record' => $activity->getKey()])
            ->assertOk()
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');
    }

    // --- Timeline -----------------------------------------------------------

    public function test_the_timeline_lists_newest_activity_first(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $this->actAs($admin);

        $oldest = $this->activity(['created_at' => now()->subHours(3)]);
        $newest = $this->activity(['created_at' => now()->subMinute()]);
        $middle = $this->activity(['created_at' => now()->subHour()]);

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true);
    }

    public function test_an_update_reads_as_a_sentence_with_old_to_new_values(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $this->actAs($admin);

        $this->activity([
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => $admin->getKey(),
            'subject_type' => self::PRODUCT,
            'subject_id' => 42,
            'properties' => [
                'old' => ['price' => '100.00', 'is_active' => false, 'status' => 'pending', 'name' => 'Mug'],
                'attributes' => ['price' => '120.00', 'is_active' => true, 'status' => 'ready_for_pickup', 'name' => 'Mug'],
            ],
        ]);

        Livewire::test(ListActivityLogs::class)
            ->assertSee($admin->name)
            ->assertSee('Product #42')
            ->assertSeeInOrder(['Price:', '₱100.00', '₱120.00'])
            ->assertSeeInOrder(['Is active:', 'No', 'Yes'])
            ->assertSeeInOrder(['Status:', 'Pending', 'Ready for pickup'])
            // "name" did not change, so it is not listed as a change.
            ->assertDontSee('Name:');
    }

    public function test_authentication_events_have_their_own_wording(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $this->actAs($admin);

        $this->activity([
            'log_name' => 'authentication',
            'event' => 'login_failed',
            'description' => 'Failed admin login attempt',
            'properties' => ['attempted_email' => 'intruder@example.com', 'ip_address' => '203.0.113.9'],
        ]);

        Livewire::test(ListActivityLogs::class)
            ->assertSeeInOrder(['Guest', 'failed to sign in as', 'intruder@example.com'])
            ->assertSee('IP 203.0.113.9');
    }

    // --- Secrets ------------------------------------------------------------

    public function test_sensitive_property_values_are_never_rendered(): void
    {
        $this->actAs($this->adminWithRole('super_admin'));

        $activity = $this->activity([
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 7,
            'properties' => [
                'old' => ['password' => 'old-hash-value', 'remember_token' => 'old-remember-value'],
                'attributes' => ['password' => 'new-hash-value', 'remember_token' => 'new-remember-value'],
                'api_token' => 'top-level-token-value',
                'client' => ['session_id' => 'nested-session-value', 'browser' => 'Firefox'],
            ],
        ]);

        $leaks = [
            'old-hash-value', 'new-hash-value', 'old-remember-value',
            'new-remember-value', 'top-level-token-value', 'nested-session-value',
        ];

        $list = Livewire::test(ListActivityLogs::class)->assertSee('Password:');
        $view = Livewire::test(ViewActivityLog::class, ['record' => $activity->getKey()])
            ->assertSee('Firefox')
            ->assertSee('Hidden');

        foreach ($leaks as $secret) {
            $list->assertDontSee($secret);
            $view->assertDontSee($secret);
        }
    }

    public function test_logged_values_are_escaped(): void
    {
        $this->actAs($this->adminWithRole('super_admin'));

        $this->activity([
            'properties' => [
                'old' => ['name' => 'Plain'],
                'attributes' => ['name' => '<script>alert(1)</script>'],
            ],
        ]);

        Livewire::test(ListActivityLogs::class)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    }

    // --- Search and filters -------------------------------------------------

    public function test_the_timeline_can_be_searched_and_filtered(): void
    {
        $admin = $this->adminWithRole('super_admin');
        $other = $this->adminWithRole('ubap');
        $this->actAs($admin);

        $login = $this->activity([
            'log_name' => 'authentication', 'event' => 'login', 'description' => 'Successful admin login',
            'causer_type' => $admin->getMorphClass(), 'causer_id' => $admin->getKey(),
        ]);
        $failed = $this->activity([
            'log_name' => 'authentication', 'event' => 'login_failed', 'description' => 'Failed admin login attempt',
        ]);
        $productUpdate = $this->activity([
            'subject_type' => self::PRODUCT, 'subject_id' => 42,
            'causer_type' => $other->getMorphClass(), 'causer_id' => $other->getKey(),
            'created_at' => now()->subDays(3),
        ]);

        Livewire::test(ListActivityLogs::class)
            ->searchTable('login_failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$login, $productUpdate]);

        Livewire::test(ListActivityLogs::class)
            ->searchTable('42')
            ->assertCanSeeTableRecords([$productUpdate])
            ->assertCanNotSeeTableRecords([$login, $failed]);

        Livewire::test(ListActivityLogs::class)
            ->searchTable($other->email)
            ->assertCanSeeTableRecords([$productUpdate])
            ->assertCanNotSeeTableRecords([$login, $failed]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('event', ['login', 'login_failed'])
            ->assertCanSeeTableRecords([$login, $failed])
            ->assertCanNotSeeTableRecords([$productUpdate]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('log_name', ['authentication'])
            ->assertCanSeeTableRecords([$login, $failed])
            ->assertCanNotSeeTableRecords([$productUpdate]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('causer', (string) $other->getKey())
            ->assertCanSeeTableRecords([$productUpdate])
            ->assertCanNotSeeTableRecords([$login, $failed]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('causer', '__none')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$login, $productUpdate]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('subject_type', [self::PRODUCT])
            ->assertCanSeeTableRecords([$productUpdate])
            ->assertCanNotSeeTableRecords([$login, $failed]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('created_at', ['from' => now()->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$login, $failed])
            ->assertCanNotSeeTableRecords([$productUpdate]);
    }

    // --- Summary ------------------------------------------------------------

    public function test_the_summary_counts_todays_logins_failures_and_changes(): void
    {
        $this->actAs($this->adminWithRole('super_admin'));

        $this->activity(['event' => 'login']);
        $this->activity(['event' => 'login_failed']);
        $this->activity(['event' => 'login_failed']);
        $this->activity(['event' => 'login_throttled']);
        $this->activity(['event' => 'created']);
        $this->activity(['event' => 'deleted']);
        $this->activity(['event' => 'logout']);
        // Yesterday in Manila: outside "today" even though it is recent.
        $this->activity(['event' => 'login_failed', 'created_at' => now()->startOfDay()->subMinute()]);

        Livewire::test(ActivityLogStats::class)
            ->assertSeeInOrder([
                'Activities today', '7',
                'Successful logins today', '1',
                'Failed login attempts today', '3',
                '1 blocked by the lockout',
                'Record changes today', '2',
            ]);
    }

    public function test_the_summary_shows_zero_when_nothing_happened_today(): void
    {
        $this->actAs($this->adminWithRole('super_admin'));

        Livewire::test(ActivityLogStats::class)
            ->assertSeeInOrder([
                'Activities today', '0',
                'Successful logins today', '0',
                'Failed login attempts today', '0',
                'Record changes today', '0',
            ]);
    }
}
