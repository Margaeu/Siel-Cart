<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboard\Widgets\DesignOverviewBannerPreview;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewRecentDesigns;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewStats;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewThemeCard;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Widgets\ActivityLogStats;
use App\Filament\Resources\ActivityLogs\Widgets\RecentActivity;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    /** @var list<class-string<Widget>> */
    private const DESIGN_WIDGETS = [
        DesignOverviewStats::class,
        DesignOverviewBannerPreview::class,
        DesignOverviewThemeCard::class,
        DesignOverviewRecentDesigns::class,
    ];

    /** @return array<class-string<Widget>|WidgetConfiguration> */
    public function getWidgets(): array
    {
        if (ActivityLogResource::isSuperAdmin()) {
            // Include registered widgets so their permission checks reflect
            // the toggles saved in Roles.
            return array_values(array_unique([
                AccountWidget::class,
                ActivityLogStats::class,
                RecentActivity::class,
                ...parent::getWidgets(),
            ]));
        }

        $user = Filament::auth()->user();

        if ($user instanceof User && $user->isDesignOnlyAdmin()) {
            return [AccountWidget::class, ...self::DESIGN_WIDGETS];
        }

        return parent::getWidgets();
    }

    public function getColumns(): int|array
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isDesignOnlyAdmin()
            ? ['default' => 1, 'lg' => 3]
            : parent::getColumns();
    }
}
