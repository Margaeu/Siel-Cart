<?php

namespace App\Filament\Pages\Dashboard\Widgets;

use App\Filament\Pages\Dashboard\Widgets\Concerns\HasDesignWidgetAccess;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Banner;
use App\Models\Theme;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Design counters shared by StratCom and authorized super admins. */
class DesignOverviewStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    // Eager, matching ActivityLogStats and RecentActivity. Widget::$isLazy
    // defaults to true, which would make each of the four Design Overview
    // widgets its own Livewire round trip after first paint -- five HTTP
    // requests to render a page whose entire cost is a handful of trivial
    // queries, and each of those requests re-pays the panel's auth and
    // role/permission lookups as well. Measured: eager renders the whole
    // dashboard in one request.
    protected static bool $isLazy = false;

    use HasDesignWidgetAccess;

    protected function getColumns(): array
    {
        return [
            'default' => 1,
            '@sm' => 2,
            '@xl' => 3,
        ];
    }

    protected function getStats(): array
    {
        ['active' => $activeBanners, 'inactive' => $inactiveBanners] = $this->bannerCounts();
        ['total' => $themeCount, 'active_name' => $activeThemeName] = $this->themeSummary();

        return [
            Stat::make('Active banners', number_format($activeBanners))
                ->description('Visible on the storefront')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('success')
                ->url(BannerResource::getUrl('index')),

            Stat::make('Inactive banners', number_format($inactiveBanners))
                ->description('Currently hidden')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->url(BannerResource::getUrl('index')),

            Stat::make('Saved themes', number_format($themeCount))
                ->description($activeThemeName !== null
                    ? "\"{$activeThemeName}\" is active"
                    : 'No theme is currently active')
                ->icon(Heroicon::OutlinedSwatch)
                ->color($activeThemeName !== null ? 'success' : 'warning')
                ->url(ThemeResource::getUrl('index')),
        ];
    }

    /**
     * Both banner counters in one round trip. SUM(CASE ...) is plain SQL, so it
     * runs the same on MySQL and on the SQLite test database -- the same reason
     * ActivityLogStats::todaysCounts() is written this way.
     *
     * `is_active = 1` rather than the Banner::active() scope because a scope
     * cannot be applied to one branch of an aggregate. It is the same
     * comparison: the column is a NOT NULL boolean, and the scope's
     * where('is_active', true) binds to 1 on both connections.
     *
     * @return array{active: int, inactive: int}
     */
    private function bannerCounts(): array
    {
        $row = Banner::query()
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 0 ELSE 1 END) AS inactive')
            ->toBase()
            ->first();

        // SUM() over zero rows is NULL, not 0.
        return [
            'active' => (int) ($row->active ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
        ];
    }

    /**
     * The saved-theme total and the active theme's name in one round trip.
     *
     * The name comes from a scalar subquery built off the Theme::active() scope
     * with limit 1, so it selects exactly the row Theme::active()->value('name')
     * would have -- and, more importantly, the same row
     * AdminPanelProvider::panel() and theme-styles.blade.php read through
     * Theme::active()->first(). Folding it into a MAX(CASE ...) over the outer
     * query would have been one query too, but it would pick the alphabetically
     * last active theme instead of the first row the database returns, which is
     * a different answer the moment anything writes a second active row behind
     * the model's booted() guard.
     *
     * With no themes at all the count is 0 and the subquery is NULL, which is
     * what the "No theme is currently active" description keys on.
     *
     * @return array{total: int, active_name: ?string}
     */
    private function themeSummary(): array
    {
        $row = Theme::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectSub(
                Theme::active()->select('name')->limit(1)->toBase(),
                'active_name',
            )
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active_name' => $row->active_name ?? null,
        ];
    }
}
