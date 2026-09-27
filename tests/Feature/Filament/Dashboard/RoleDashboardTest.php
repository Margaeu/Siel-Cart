<?php

namespace Tests\Feature\Filament\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewBannerPreview;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewRecentDesigns;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewStats;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewThemeCard;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Widgets\ActivityLogStats;
use App\Filament\Resources\ActivityLogs\Widgets\RecentActivity;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\InventoryManagement;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UnitSold;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The panel has one dashboard page at one URL, and it shows a different set of
 * widgets depending on who is looking.
 *
 * A super admin lands on system oversight — today's audit totals and the
 * newest entries in the trail — and explicitly *not* on shop operations, which
 * Shield would never have filtered out for them since super_admin bypasses
 * permission checks. A stratcom-only admin (User::isDesignOnlyAdmin()) lands
 * on the Design Overview instead — see DesignOverviewDashboardTest for that
 * content in detail. Every other role, including a stratcom admin who also
 * holds ubap or super_admin, keeps exactly the operations dashboard it had.
 *
 * Authorization is asserted separately from dashboard content throughout:
 * leaving a widget off a dashboard hides it, each widget's own canView() is
 * what protects it.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** The operations widgets discovered onto the panel from app/Filament/Widgets. */
    private const OPERATIONS_WIDGETS = [
        StatsOverview::class,
        UnitSold::class,
        InventoryManagement::class,
    ];

    private const AUDIT_WIDGETS = [
        ActivityLogStats::class,
        RecentActivity::class,
    ];

    /** The View:<Widget> keys Shield generates for the operations widgets. */
    private const WIDGET_PERMISSIONS = [
        'View:StatsOverview',
        'View:UnitSold',
        'View:InventoryManagement',
    ];

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
     *
     * @param  list<string>  $roles
     */
    private function admin(array $roles = [], bool $withWidgetPermissions = false): User
    {
        $user = new User;
        $user->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin.'.Str::random(8).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();

        foreach ($roles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($withWidgetPermissions) {
                // Granted through the role so "ubap keeps its widgets" is a
                // real permission check rather than an unguarded pass.
                foreach (self::WIDGET_PERMISSIONS as $permission) {
                    $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
                }
            }

            $user->assignRole($role);
        }

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
     * What the dashboard would actually render: the role's widget list with
     * each widget's own canView() applied, exactly as Filament does.
     *
     * @return list<class-string>
     */
    private function visibleWidgetsFor(User $user): array
    {
        $this->actAs($user);

        return array_values(Livewire::test(Dashboard::class)->instance()->getVisibleWidgets());
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

    // --- Navigation and URL -------------------------------------------------

    public function test_the_dashboard_keeps_one_route_at_the_panel_root(): void
    {
        $this->assertTrue(Route::has('filament.admin.pages.dashboard'));
        $this->assertSame('/admin', parse_url(Dashboard::getUrl(panel: 'admin'), PHP_URL_PATH));

        // One registration, one navigation entry — the custom page replaces
        // Filament's rather than sitting alongside it.
        $registered = array_values(array_filter(
            Filament::getPanel('admin')->getPages(),
            fn (string $page): bool => is_a($page, \Filament\Pages\Dashboard::class, allow_string: true),
        ));

        $this->assertSame([Dashboard::class], $registered);
    }

    // --- Widget selection by role -------------------------------------------

    public function test_a_super_admin_gets_the_audit_widgets_and_not_the_operations_widgets(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['super_admin']));

        $this->assertSame([
            AccountWidget::class,
            ActivityLogStats::class,
            RecentActivity::class,
        ], $widgets);

        foreach (self::OPERATIONS_WIDGETS as $widget) {
            $this->assertNotContains($widget, $widgets);
        }
    }

    public function test_a_ubap_admin_keeps_its_authorized_operations_widgets_and_gets_no_audit_widgets(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['ubap'], withWidgetPermissions: true));

        foreach (self::OPERATIONS_WIDGETS as $widget) {
            $this->assertContains($widget, $widgets);
        }

        foreach (self::AUDIT_WIDGETS as $widget) {
            $this->assertNotContains($widget, $widgets);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonSuperAdminRoles(): array
    {
        return [
            'ubap' => ['ubap'],
            'stratcom' => ['stratcom'],
        ];
    }

    /**
     * The operations widgets are still Shield-gated for a ubap admin:
     * without the View:<Widget> permission they drop out, which is the
     * behaviour that existed before the dashboard was split by role. Only
     * ubap is exercised here — a stratcom admin no longer keeps the panel's
     * own widget list at all (see test_a_stratcom_only_admin_gets_the_design_overview_widgets
     * below), so it would trivially pass this assertion for an unrelated
     * reason rather than testing Shield gating.
     */
    public function test_a_non_super_admin_without_widget_permissions_sees_no_operations_widgets(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['ubap']));

        $this->assertSame([AccountWidget::class], $widgets);
    }

    /**
     * A stratcom-only admin's dashboard is App\Filament\Pages\Dashboard\Widgets\DesignOverview*
     * (see tests/Feature/Filament/DesignOverviewDashboardTest.php for that
     * content in detail) — not the panel's registered/discovered widget
     * list, and not the audit widgets either. Widget permissions granted
     * here make no difference: the branch is decided by role
     * (User::isDesignOnlyAdmin()), same as the super-admin branch above.
     */
    public function test_a_stratcom_only_admin_gets_the_design_overview_widgets(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['stratcom'], withWidgetPermissions: true));

        $this->assertSame([
            DesignOverviewStats::class,
            DesignOverviewBannerPreview::class,
            DesignOverviewThemeCard::class,
            DesignOverviewRecentDesigns::class,
        ], $widgets);

        foreach (self::OPERATIONS_WIDGETS as $widget) {
            $this->assertNotContains($widget, $widgets);
        }

        foreach (self::AUDIT_WIDGETS as $widget) {
            $this->assertNotContains($widget, $widgets);
        }

        $this->assertNotContains(AccountWidget::class, $widgets);
    }

    /**
     * A stratcom admin who also holds ubap is not design-only
     * (User::isDesignOnlyAdmin()), so they keep the ordinary operations
     * dashboard untouched -- the same "a second role wins" rule the
     * super-admin branch follows, just in the other direction.
     */
    public function test_a_stratcom_admin_with_a_second_role_keeps_that_roles_dashboard(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['stratcom', 'ubap'], withWidgetPermissions: true));

        foreach (self::OPERATIONS_WIDGETS as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertNotContains(DesignOverviewStats::class, $widgets);
    }

    public function test_super_admin_takes_precedence_over_a_second_role(): void
    {
        $widgets = $this->visibleWidgetsFor($this->admin(['ubap', 'super_admin'], withWidgetPermissions: true));

        $this->assertSame([
            AccountWidget::class,
            ActivityLogStats::class,
            RecentActivity::class,
        ], $widgets);

        foreach (self::OPERATIONS_WIDGETS as $widget) {
            $this->assertNotContains($widget, $widgets);
        }
    }

    // --- Audit widget authorization -----------------------------------------

    #[DataProvider('nonSuperAdminRoles')]
    public function test_the_audit_widgets_refuse_every_non_super_admin_on_the_server(string $role): void
    {
        $this->actAs($this->admin([$role], withWidgetPermissions: true));

        // Not "they are absent from the dashboard" — each widget is its own
        // Livewire component, so this is the check that actually protects them.
        $this->assertFalse(ActivityLogStats::canView());
        $this->assertFalse(RecentActivity::canView());
        $this->assertFalse(ActivityLogResource::canViewAny());
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_a_non_super_admin_cannot_open_the_activity_log(string $role): void
    {
        $admin = $this->admin([$role], withWidgetPermissions: true);

        $this->actingAs($admin)
            ->get(ActivityLogResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_the_activity_log_stays_read_only_for_super_admins_too(): void
    {
        $this->actAs($this->admin(['super_admin']));
        $activity = $this->activity();

        $this->assertTrue(ActivityLogStats::canView());
        $this->assertTrue(RecentActivity::canView());

        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($activity));
        $this->assertFalse(ActivityLogResource::canDelete($activity));
        $this->assertFalse(ActivityLogResource::canDeleteAny());
    }

    // --- Recent activity content --------------------------------------------

    public function test_recent_activity_shows_at_most_ten_entries_newest_first(): void
    {
        $this->actAs($this->admin(['super_admin']));

        // Twelve, one minute apart, so "newest first" and "only ten" are both
        // provable and neither depends on insertion order.
        $created = collect(range(1, 12))->map(fn (int $minute): Activity => $this->activity([
            'created_at' => Carbon::parse('2026-09-19 08:00:00', 'Asia/Manila')->addMinutes($minute),
            'subject_type' => 'App\\Models\\Product',
            'subject_id' => $minute,
        ]));

        $activities = Livewire::test(RecentActivity::class)->instance()->getActivities();

        $this->assertCount(RecentActivity::LIMIT, $activities);
        $this->assertSame(
            $created->sortByDesc('created_at')->take(RecentActivity::LIMIT)->pluck('id')->values()->all(),
            $activities->pluck('id')->all(),
        );
    }

    /**
     * Rows written in the same second must still come back in a fixed order —
     * a sign-in and the model write that follows it routinely share a
     * timestamp, and without the id tiebreaker the list would shuffle.
     */
    public function test_entries_sharing_a_timestamp_are_ordered_by_id(): void
    {
        $this->actAs($this->admin(['super_admin']));

        $at = Carbon::parse('2026-09-19 09:00:00', 'Asia/Manila');
        $first = $this->activity(['created_at' => $at]);
        $second = $this->activity(['created_at' => $at]);
        $third = $this->activity(['created_at' => $at]);

        $ids = Livewire::test(RecentActivity::class)->instance()->getActivities()->pluck('id')->all();

        $this->assertSame([$third->id, $second->id, $first->id], $ids);
    }

    /**
     * The presenter reads a name off each row's causer and subject, so without
     * the eager load the card would cost two queries per entry. The count must
     * not grow with the number of entries rendered.
     */
    public function test_rendering_the_card_costs_the_same_whatever_it_lists(): void
    {
        $admin = $this->admin(['super_admin']);
        $this->actAs($admin);

        $count = function (int $entries) use ($admin): int {
            Activity::query()->delete();

            foreach (range(1, $entries) as $i) {
                $this->activity([
                    'causer_type' => $admin->getMorphClass(),
                    'causer_id' => $admin->getKey(),
                    'subject_type' => $admin->getMorphClass(),
                    'subject_id' => $admin->getKey(),
                    'created_at' => Carbon::parse('2026-09-19 08:00:00', 'Asia/Manila')->addMinutes($i),
                ]);
            }

            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            Livewire::test(RecentActivity::class)->assertOk();

            return $queries;
        };

        // Warm-up render first: Spatie's permission registrar loads the
        // permissions table once per process, and whichever render happens to
        // go first would otherwise carry that one extra query.
        $count(1);

        $this->assertSame($count(2), $count(RecentActivity::LIMIT));
    }

    public function test_the_widget_renders_an_empty_state_when_nothing_has_been_recorded(): void
    {
        $this->actAs($this->admin(['super_admin']));

        $this->assertSame(0, Activity::query()->count());

        Livewire::test(RecentActivity::class)
            ->assertOk()
            ->assertSee('No activity recorded yet');
    }

    public function test_the_widget_links_to_the_activity_log_resource(): void
    {
        $this->actAs($this->admin(['super_admin']));
        $activity = $this->activity(['subject_type' => 'App\\Models\\Product', 'subject_id' => 42]);

        Livewire::test(RecentActivity::class)
            ->assertOk()
            ->assertSee('Recent admin activity')
            ->assertSee('Product #42')
            ->assertSee(ActivityLogResource::getUrl('view', ['record' => $activity]), escape: false)
            ->assertSee('View all activity logs')
            ->assertSee(ActivityLogResource::getUrl('index'), escape: false);
    }

    /**
     * A real super admin holds every generated permission, because Shield is
     * configured with `define_via_gate => false` (config/filament-shield.php),
     * which grants the role the permissions outright rather than intercepting
     * the gate. The two Shield keys are granted here for the same reason.
     */
    public function test_the_shortcuts_offer_users_and_roles_to_an_authorized_admin(): void
    {
        $admin = $this->admin(['super_admin']);
        $admin->roles->first()->givePermissionTo(
            Permission::findOrCreate('ViewAny:User', 'web'),
            Permission::findOrCreate('ViewAny:Role', 'web'),
        );
        $this->actAs($admin);

        $labels = array_column(Livewire::test(RecentActivity::class)->instance()->getShortcuts(), 'label');

        $this->assertSame(['View all activity logs', 'Users', 'Roles'], $labels);
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(RoleResource::canViewAny());
    }

    /**
     * The shortcuts are authorization-aware, so one can never offer a page
     * that would then refuse the admin who clicked it. "View all activity
     * logs" always stands: the widget is only visible to someone who may open
     * the resource in the first place.
     */
    public function test_the_shortcuts_omit_pages_the_admin_may_not_open(): void
    {
        $this->actAs($this->admin(['super_admin']));

        $labels = array_column(Livewire::test(RecentActivity::class)->instance()->getShortcuts(), 'label');

        $this->assertSame(['View all activity logs'], $labels);
    }

    // --- End to end ----------------------------------------------------------

    /**
     * Taking the operations widgets off the super admin's landing page is a
     * dashboard-layout change only: every shop resource is still reachable
     * from the sidebar, and the widgets themselves still render for a super
     * admin who opens one directly.
     */
    public function test_a_super_admin_still_reaches_shop_management(): void
    {
        $this->withoutVite();
        $admin = $this->admin(['super_admin']);
        $admin->roles->first()->givePermissionTo(
            Permission::findOrCreate('ViewAny:Order', 'web'),
            Permission::findOrCreate('View:UnitSold', 'web'),
        );
        $this->actAs($admin);

        $this->assertTrue(OrderResource::canViewAny());
        $this->assertTrue(UnitSold::canView());

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('index'))
            ->assertOk();
    }

    public function test_a_super_admin_opening_the_dashboard_sees_the_audit_cards_and_not_the_operations_ones(): void
    {
        $this->withoutVite();
        $admin = $this->admin(['super_admin']);
        $this->activity(['subject_type' => 'App\\Models\\Product', 'subject_id' => 7]);

        $this->actingAs($admin)
            ->get(Dashboard::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Activities today')
            ->assertSee('Successful logins today')
            ->assertSee('Failed login attempts today')
            ->assertSee('Record changes today')
            ->assertSee('Recent admin activity')
            ->assertDontSee('Pending orders')
            ->assertDontSee('Units Sold');
    }
}
