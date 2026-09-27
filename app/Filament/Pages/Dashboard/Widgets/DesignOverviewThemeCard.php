<?php

namespace App\Filament\Pages\Dashboard\Widgets;

use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * "Active color theme" -- the one Theme row the storefront and the panel
 * itself currently read (Theme::active(), the same scope
 * AdminPanelProvider and theme-styles.blade.php use). When no theme is
 * active the view names the real fallback those two fall back to rather
 * than implying nothing is themed at all.
 */
class DesignOverviewThemeCard extends Widget
{
    protected string $view = 'filament.pages.dashboard.design-overview-theme-card';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

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

    protected function getViewData(): array
    {
        return [
            'theme' => Theme::active()->first(),
            'manageThemesUrl' => ThemeResource::getUrl('index'),
        ];
    }
}
