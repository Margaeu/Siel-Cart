<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewBannerPreview;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewRecentDesigns;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewStats;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewThemeCard;
use App\Filament\Resources\Banners\BannerResource;
use App\Models\Banner;
use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The stratcom-only "Design Overview" dashboard: role isolation for the four
 * widgets in App\Filament\Pages\Dashboard\Widgets, the counts and truthful
 * theme messaging on DesignOverviewStats, active-banner ordering and the
 * selection interaction on DesignOverviewBannerPreview, the fallback
 * explanation on DesignOverviewThemeCard, and the combined/sorted/limited
 * list on DesignOverviewRecentDesigns.
 *
 * Permissions are real Shield permissions, not a blanket Gate::before, so
 * User::isDesignOnlyAdmin() and each widget's own canView() are actually
 * exercised rather than bypassed.
 */
class DesignOverviewDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake('r2');
    }

    private function makeAdminWithRoles(array $roles): User
    {
        static $counter = 0;
        $counter++;

        $user = new User;
        $user->forceFill([
            'first_name' => 'Test',
            'last_name' => "Admin{$counter}",
            'email' => "design.overview.{$counter}@example.com",
            'password' => 'password',
            'is_active' => true,
        ])->save();

        foreach ($roles as $role) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }

    /**
     * StatsOverviewWidget::getCachedStats() is protected, and the rendered
     * numbers are too generic to assert on ("0" appears all over a panel page),
     * so the stats are read off the widget directly.
     *
     * @return array<string, string>
     */
    private function statValues(DesignOverviewStats $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getStats');

        return collect($method->invoke($widget))
            ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()])
            ->all();
    }

    private function makeBanner(bool $isActive, int $sortOrder = 0, ?string $imagePath = null): Banner
    {
        return Banner::create([
            'image_path' => $imagePath ?? 'banners/'.Str::random(8).'.jpg',
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
    }

    // --- User::isDesignOnlyAdmin() -----------------------------------

    public function test_stratcom_only_admin_is_design_only_admin(): void
    {
        $user = $this->makeAdminWithRoles(['stratcom']);

        $this->assertTrue($user->isDesignOnlyAdmin());
    }

    public function test_stratcom_with_super_admin_is_not_design_only_admin(): void
    {
        $user = $this->makeAdminWithRoles(['stratcom', 'super_admin']);

        $this->assertFalse($user->isDesignOnlyAdmin());
    }

    public function test_stratcom_with_ubap_is_not_design_only_admin(): void
    {
        $user = $this->makeAdminWithRoles(['stratcom', 'ubap']);

        $this->assertFalse($user->isDesignOnlyAdmin());
    }

    public function test_ubap_alone_is_not_design_only_admin(): void
    {
        $user = $this->makeAdminWithRoles(['ubap']);

        $this->assertFalse($user->isDesignOnlyAdmin());
    }

    // --- Widget-level role isolation (canView) ------------------------

    private const WIDGET_CLASSES = [
        DesignOverviewStats::class,
        DesignOverviewBannerPreview::class,
        DesignOverviewThemeCard::class,
        DesignOverviewRecentDesigns::class,
    ];

    public function test_design_overview_widgets_are_visible_to_a_stratcom_only_admin(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        foreach (self::WIDGET_CLASSES as $widgetClass) {
            $this->assertTrue($widgetClass::canView(), "{$widgetClass}::canView() should be true for a stratcom-only admin.");
        }
    }

    public function test_super_admin_design_widgets_are_hidden_without_widget_permissions(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom', 'super_admin']));

        foreach (self::WIDGET_CLASSES as $widgetClass) {
            $this->assertFalse($widgetClass::canView(), "{$widgetClass}::canView() should be false once the admin also holds super_admin.");
        }
    }

    public function test_design_overview_widgets_are_hidden_from_a_ubap_admin(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['ubap']));

        foreach (self::WIDGET_CLASSES as $widgetClass) {
            $this->assertFalse($widgetClass::canView(), "{$widgetClass}::canView() should be false for a ubap admin with no stratcom role.");
        }
    }

    public function test_design_overview_widgets_are_hidden_when_no_one_is_authenticated(): void
    {
        foreach (self::WIDGET_CLASSES as $widgetClass) {
            $this->assertFalse($widgetClass::canView());
        }
    }

    // --- DesignOverviewStats -------------------------------------------

    public function test_stats_widget_reports_active_and_inactive_banner_counts_and_active_theme(): void
    {
        $this->makeBanner(true);
        $this->makeBanner(true);
        $this->makeBanner(false);
        $this->makeBanner(false);
        $this->makeBanner(false);
        Theme::create(['name' => 'Default Green', 'primary_color' => '#1E6031', 'secondary_color' => '#E0A70D', 'is_active' => true]);
        Theme::create(['name' => 'Christmas Promo', 'primary_color' => '#B0231E', 'secondary_color' => '#0F7A3D', 'is_active' => false]);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewStats::class)
            ->assertSee('2') // active banners
            ->assertSee('3') // inactive banners
            ->assertSee('Saved themes')
            ->assertSee('"Default Green" is active');
    }

    public function test_stats_widget_is_truthful_when_no_theme_is_active(): void
    {
        Theme::create(['name' => 'Retired Promo', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => false]);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewStats::class)
            ->assertSee('No theme is currently active');
    }

    // --- DesignOverviewBannerPreview ------------------------------------

    public function test_banner_preview_only_lists_active_banners_in_storefront_order(): void
    {
        $last = $this->makeBanner(true, 3);
        $first = $this->makeBanner(true, 1);
        $this->makeBanner(false, 0);
        $middle = $this->makeBanner(true, 2);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $widget = new DesignOverviewBannerPreview;
        $ids = $widget->banners()->pluck('id')->all();

        $this->assertSame([$first->id, $middle->id, $last->id], $ids);
    }

    public function test_banner_preview_selection_moves_the_selected_slide_and_ignores_out_of_range(): void
    {
        $this->makeBanner(true, 1);
        $this->makeBanner(true, 2);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewBannerPreview::class)
            ->assertSet('selectedIndex', 0)
            ->call('selectBanner', 1)
            ->assertSet('selectedIndex', 1)
            ->assertSee('Banner 2 of 2')
            ->call('selectBanner', 99)
            ->assertSet('selectedIndex', 1);
    }

    public function test_banner_preview_shows_empty_state_when_no_banners_are_active(): void
    {
        $this->makeBanner(false);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewBannerPreview::class)
            ->assertSee('No banners are active right now');
    }

    public function test_banner_preview_handles_a_missing_image_path_gracefully(): void
    {
        $banner = new Banner;
        $banner->forceFill(['image_path' => '', 'is_active' => true, 'sort_order' => 1])->save();

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewBannerPreview::class)
            ->assertOk()
            ->assertSee('Image file is missing');
    }

    // --- DesignOverviewThemeCard -----------------------------------------

    public function test_theme_card_shows_the_active_theme_and_its_hex_codes(): void
    {
        Theme::create(['name' => 'Christmas Promo', 'primary_color' => '#1E6031', 'secondary_color' => '#E0A70D', 'is_active' => true]);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewThemeCard::class)
            ->assertSee('Christmas Promo')
            ->assertSee('In use')
            ->assertSee('#1E6031')
            ->assertSee('#E0A70D');
    }

    public function test_theme_card_explains_the_storefront_fallback_when_no_theme_is_active(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(DesignOverviewThemeCard::class)
            ->assertSee('No theme is currently marked active')
            ->assertSee('#557F13')
            ->assertSee('#FFD801');
    }

    // --- DesignOverviewRecentDesigns -------------------------------------

    public function test_recent_designs_combines_banners_and_themes_sorted_by_updated_at_and_limited_to_five(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $banner1 = $this->makeBanner(true);

        Carbon::setTestNow('2026-01-02 00:00:00');
        Theme::create(['name' => 'Theme One', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true]);

        Carbon::setTestNow('2026-01-03 00:00:00');
        $this->makeBanner(false);

        // Six more banners, each touched most recently, to prove the list
        // caps at 5 and that the oldest of the batch above (banner1) drops
        // off the combined list even though it would still be in banner-only
        // top 5.
        for ($i = 1; $i <= 6; $i++) {
            $banner = $this->makeBanner(true);
            Carbon::setTestNow(Carbon::parse('2026-01-04 00:00:00')->addMinutes($i));
            $banner->touch();
        }

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $rows = (new DesignOverviewRecentDesigns)->recentDesigns();

        $this->assertCount(5, $rows);
        $this->assertTrue($rows->pluck('key')->doesntContain("banner-{$banner1->id}"));

        $updatedTimestamps = $rows->pluck('updated_at')->map(fn ($d) => $d->timestamp)->all();
        $this->assertSame(collect($updatedTimestamps)->sortDesc()->values()->all(), $updatedTimestamps);
    }

    public function test_recent_designs_labels_a_banner_by_id_and_a_theme_by_name_with_correct_statuses(): void
    {
        $banner = $this->makeBanner(true);
        $theme = Theme::create(['name' => 'Christmas Promo', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => false]);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $rows = (new DesignOverviewRecentDesigns)->recentDesigns()->keyBy('key');

        $this->assertSame("Banner #{$banner->id}", $rows["banner-{$banner->id}"]['label']);
        $this->assertSame('Active', $rows["banner-{$banner->id}"]['status']);

        $this->assertSame('Christmas Promo', $rows["theme-{$theme->id}"]['label']);
        $this->assertSame('Inactive', $rows["theme-{$theme->id}"]['status']);
    }

    public function test_recent_designs_only_links_records_the_admin_is_authorized_to_open(): void
    {
        $banner = $this->makeBanner(true);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        // No Shield permissions granted yet: BannerResource::canEdit()/canView()
        // are both false, so the row must not link anywhere.
        $rows = (new DesignOverviewRecentDesigns)->recentDesigns()->keyBy('key');
        $this->assertNull($rows["banner-{$banner->id}"]['url']);

        foreach (['ViewAny:Banner', 'View:Banner', 'Update:Banner'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findByName('stratcom', 'web')->givePermissionTo(['ViewAny:Banner', 'View:Banner', 'Update:Banner']);

        $rows = (new DesignOverviewRecentDesigns)->recentDesigns()->keyBy('key');
        $this->assertSame(
            BannerResource::getUrl('edit', ['record' => $banner]),
            $rows["banner-{$banner->id}"]['url'],
        );
    }

    // --- Dashboard page chrome (heading, subheading, header actions, columns) ---

    public function test_stratcom_dashboard_has_a_simple_welcome_card_and_no_header_actions(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom']));
        Permission::findOrCreate('Create:Banner', 'web');
        Role::findByName('stratcom', 'web')->givePermissionTo('Create:Banner');

        $page = Livewire::test(Dashboard::class);

        $this->assertSame('Dashboard', $page->instance()->getHeading());
        $this->assertNull($page->instance()->getSubheading());
        $this->assertSame(['default' => 1, 'lg' => 3], $page->instance()->getColumns());

        $page->assertSeeText('Welcome')->assertSeeText('Sign out')->assertDontSeeText('View storefront')
            ->assertDontSeeText('Upload banner');
    }

    public function test_dashboard_page_hides_the_upload_banner_action_without_permission(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        Livewire::test(Dashboard::class)
            ->assertDontSeeText('View storefront')
            ->assertDontSeeText('Upload banner');
    }

    public function test_dashboard_page_keeps_the_stock_chrome_for_every_other_admin(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['ubap']));

        $page = Livewire::test(Dashboard::class);

        $this->assertNotSame('Design Overview', $page->instance()->getHeading());
        $this->assertNull($page->instance()->getSubheading());
        $this->assertSame(2, $page->instance()->getColumns());

        $page->assertDontSeeText('Upload banner');
    }

    // --- Aggregates and request-local reuse ------------------------------
    //
    // DesignOverviewStats folds four queries into two (one banner aggregate,
    // one theme aggregate) and DesignOverviewBannerPreview reuses its
    // collection for the length of a request. These pin the behaviour those
    // rewrites had to preserve, which plain output assertions above would not
    // have caught.

    /**
     * SUM() over zero rows is NULL, not 0, and COUNT(*) with a scalar subquery
     * over an empty table still returns one row. Both counters must read 0
     * rather than an empty string, and the theme card must take the "no active
     * theme" branch rather than quoting a NULL name.
     */
    public function test_stats_widget_reads_zero_from_empty_tables(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $stats = Livewire::test(DesignOverviewStats::class)
            ->assertOk()
            ->assertSee('Active banners')
            ->assertSee('Inactive banners')
            ->assertSee('No theme is currently active');

        $values = $this->statValues($stats->instance());

        $this->assertSame('0', $values['Active banners']);
        $this->assertSame('0', $values['Inactive banners']);
        $this->assertSame('0', $values['Saved themes']);
    }

    /**
     * The active theme's name comes from a scalar subquery now. It has to keep
     * naming the row Theme::active()->first() names -- the row the storefront
     * and the panel itself actually render -- including when a second active
     * row exists, which a raw write can produce behind Theme::booted()'s
     * single-active guard.
     */
    public function test_stats_widget_names_the_same_active_theme_the_storefront_uses(): void
    {
        // Raw inserts: Eloquent would switch the first one off on save.
        DB::table('themes')->insert([
            ['name' => 'Zulu Theme', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Alpha Theme', 'primary_color' => '#333333', 'secondary_color' => '#444444', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        // Not simply "the first row": whatever Theme::active()->first() answers
        // is the contract, so the assertion reads it the same way the panel does
        // instead of hardcoding a winner.
        $expected = Theme::active()->first()->name;

        Livewire::test(DesignOverviewStats::class)
            ->assertOk()
            ->assertSee("\"{$expected}\" is active")
            ->assertSee('2'); // saved themes
    }

    /**
     * selectBanner()'s bounds check and the render that follows it share one
     * request, so they must share one query rather than each fetching the
     * carousel.
     */
    public function test_banner_preview_queries_the_carousel_once_per_request(): void
    {
        $this->makeBanner(true, 1);
        $this->makeBanner(true, 2);
        $this->makeBanner(true, 3);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $bannerQueries = 0;
        DB::listen(function ($query) use (&$bannerQueries): void {
            if (str_contains($query->sql, 'from "banners"')) {
                $bannerQueries++;
            }
        });

        $widget = Livewire::test(DesignOverviewBannerPreview::class)->assertOk();

        // The mount is its own request and legitimately costs one query; what
        // is being measured is the selectBanner request that follows, where the
        // bounds check and the re-render used to fetch the carousel separately.
        $this->assertSame(1, $bannerQueries, 'mount should cost one carousel query');
        $bannerQueries = 0;

        $widget->call('selectBanner', 2)
            ->assertSet('selectedIndex', 2);

        $this->assertSame(1, $bannerQueries);
    }

    /**
     * The reuse above is request-local and must not outlive the request: a
     * banner another admin switches off has to disappear on the next one. This
     * is why the cache is a plain private property rather than a persisted
     * computed property.
     */
    public function test_banner_preview_does_not_carry_a_stale_carousel_between_requests(): void
    {
        $first = $this->makeBanner(true, 1);
        $second = $this->makeBanner(true, 2);

        $this->actingAs($this->makeAdminWithRoles(['stratcom']));

        $widget = Livewire::test(DesignOverviewBannerPreview::class);
        $this->assertSame([$first->id, $second->id], (new DesignOverviewBannerPreview)->banners()->pluck('id')->all());

        $second->update(['is_active' => false]);

        // Selecting the now-missing index is refused, and the next render falls
        // back to the first slide instead of showing the deactivated banner.
        $widget->call('selectBanner', 1)
            ->assertSet('selectedIndex', 0)
            ->assertOk();

        $this->assertSame([$first->id], (new DesignOverviewBannerPreview)->banners()->pluck('id')->all());
    }

    // --- The shared dashboard Welcome card ---

    public function test_the_dashboard_welcome_card_shows_the_design_admins_full_name(): void
    {
        $admin = $this->makeAdminWithRoles(['stratcom']);
        $this->actingAs($admin);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('Welcome')->assertSee('Sign out')->assertSee(Filament::getUserName($admin))->assertDontSee('Welcome back,');
    }

    public function test_the_dashboard_greeting_escapes_the_admins_name(): void
    {
        $admin = $this->makeAdminWithRoles(['stratcom']);
        $admin->forceFill(['first_name' => 'Bobby<script>alert(1)</script>'])->save();
        $this->actingAs($admin);

        $response = $this->get(Dashboard::getUrl())->assertOk();

        // Escaped, and the raw tag never reaches the document.
        $response->assertSee('Bobby&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('Bobby<script>', false);
    }

    public function test_other_admins_do_not_have_the_old_design_greeting(): void
    {
        $this->actingAs($this->makeAdminWithRoles(['ubap']));

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('Welcome back,');
    }
}
