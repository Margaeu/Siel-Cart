<?php

namespace App\Filament\Pages\Dashboard\Widgets;

use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Banner;
use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * "Recently updated designs" -- the 5 most recently touched banners and
 * themes combined, newest first. Banners and themes share no table, so this
 * is built in PHP rather than as one query: each side's own most-recent
 * self::LIMIT rows is enough, because a row that misses a table's own top
 * self::LIMIT can never place in the combined top self::LIMIT either -- at
 * least that many rows from its own table alone would already outrank it.
 */
class DesignOverviewRecentDesigns extends Widget
{
    protected string $view = 'filament.pages.dashboard.design-overview-recent-designs';

    protected int|string|array $columnSpan = 'full';

    private const LIMIT = 5;

    // Eager, matching ActivityLogStats and RecentActivity. Widget::$isLazy
    // defaults to true, which would make each of the four Design Overview
    // widgets its own Livewire round trip after first paint -- five HTTP
    // requests to render a page whose entire cost is a handful of trivial
    // queries, and each of those requests re-pays the panel's auth and
    // role/permission lookups as well. Measured: eager renders the whole
    // dashboard in one request.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Filament::auth()?->user();

        return $user instanceof User && $user->isDesignOnlyAdmin();
    }

    public function recentDesigns(): Collection
    {
        $banners = Banner::query()
            ->latest('updated_at')
            ->take(self::LIMIT)
            ->get()
            ->map(fn (Banner $banner): array => [
                'key' => "banner-{$banner->id}",
                // Banner has no name/title column (see BannersTable), so the
                // id is the only stable label an admin can act on.
                'label' => "Banner #{$banner->id}",
                'type' => 'Banner',
                'status' => $banner->is_active ? 'Active' : 'Inactive',
                'status_color' => $banner->is_active ? 'success' : 'danger',
                'updated_at' => $banner->updated_at,
                'kind' => 'banner',
                'image_url' => $banner->is_video ? null : $banner->image_url,
                'is_video' => $banner->is_video,
                'swatch_colors' => null,
                'url' => $this->bannerUrl($banner),
            ]);

        $themes = Theme::query()
            ->latest('updated_at')
            ->take(self::LIMIT)
            ->get()
            ->map(fn (Theme $theme): array => [
                'key' => "theme-{$theme->id}",
                'label' => $theme->name,
                'type' => 'Color theme',
                'status' => $theme->is_active ? 'In use' : 'Inactive',
                'status_color' => $theme->is_active ? 'success' : 'gray',
                'updated_at' => $theme->updated_at,
                'kind' => 'theme',
                'image_url' => null,
                'is_video' => false,
                'swatch_colors' => [$theme->primary_color, $theme->secondary_color],
                'url' => $this->themeUrl($theme),
            ]);

        return $banners->concat($themes)
            ->sortByDesc(fn (array $row) => $row['updated_at'])
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * The edit page when the admin can change the banner, the read-only
     * view when they can only look, and no link at all rather than one that
     * 403s -- whichever Shield permissions this admin's role actually holds.
     */
    private function bannerUrl(Banner $banner): ?string
    {
        if (BannerResource::canEdit($banner)) {
            return BannerResource::getUrl('edit', ['record' => $banner]);
        }

        if (BannerResource::canView($banner)) {
            return BannerResource::getUrl('view', ['record' => $banner]);
        }

        return null;
    }

    private function themeUrl(Theme $theme): ?string
    {
        if (ThemeResource::canEdit($theme)) {
            return ThemeResource::getUrl('edit', ['record' => $theme]);
        }

        if (ThemeResource::canView($theme)) {
            return ThemeResource::getUrl('view', ['record' => $theme]);
        }

        return null;
    }

    protected function getViewData(): array
    {
        return [
            'rows' => $this->recentDesigns(),
        ];
    }
}
