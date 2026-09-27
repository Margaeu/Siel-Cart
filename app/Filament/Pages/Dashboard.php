<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboard\Widgets\DesignOverviewBannerPreview;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewRecentDesigns;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewStats;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewThemeCard;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Widgets\ActivityLogStats;
use App\Filament\Resources\ActivityLogs\Widgets\RecentActivity;
use App\Filament\Resources\Banners\BannerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The panel's one dashboard, showing a different set of widgets — and, for
 * one role, a different heading, column layout, and header actions — per
 * role.
 *
 * Registered in place of Filament\Pages\Dashboard, not alongside it: both
 * resolve to the slug "dashboard" and so to the same URL and the same
 * navigation entry, and registering the two would duplicate them.
 *
 * Only the *content* of the dashboard is decided here. Whether a widget may be
 * rendered at all is each widget's own canView() — HasWidgetShield on the
 * operations widgets, the super-admin rule on the audit ones, User::isDesignOnlyAdmin()
 * on the Design Overview ones — which Filament applies to whatever this
 * returns. That separation matters because a widget is a Livewire component
 * in its own right: leaving one off a dashboard hides it, it does not
 * protect it.
 */
class Dashboard extends BaseDashboard
{
    /**
     * A super admin's landing page is system oversight, not shop operations:
     * today's audit totals, the newest entries in the trail, and the links out
     * to the log, Users, and Roles. The operations widgets (StatsOverview,
     * UnitSold, InventoryManagement) are left off deliberately — a super admin
     * still reaches every shop resource from the sidebar, and Shield would not
     * have filtered those widgets out on its own, since super_admin bypasses
     * permission checks entirely.
     *
     * AccountWidget stays because it is panel chrome ("Hello, <name>"), not an
     * operations widget.
     *
     * @var list<class-string<Widget>>
     */
    private const SUPER_ADMIN_WIDGETS = [
        AccountWidget::class,
        ActivityLogStats::class,
        RecentActivity::class,
    ];

    /**
     * @var list<class-string<Widget>>
     */
    private const DESIGN_ONLY_WIDGETS = [
        DesignOverviewStats::class,
        DesignOverviewBannerPreview::class,
        DesignOverviewThemeCard::class,
        DesignOverviewRecentDesigns::class,
    ];

    /**
     * Which of the three landing pages this request gets, checked in one
     * place so every method below agrees and a super admin always wins over
     * a second role. super_admin is checked first even though
     * User::isDesignOnlyAdmin() already excludes it on its own — that
     * exclusion living somewhere else is exactly why the order is pinned
     * here rather than left implicit.
     */
    private function currentDashboardVariant(): string
    {
        if (ActivityLogResource::isSuperAdmin()) {
            return 'super_admin';
        }

        $user = Filament::auth()->user();

        if ($user instanceof User && $user->isDesignOnlyAdmin()) {
            return 'design_only';
        }

        return 'default';
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return match ($this->currentDashboardVariant()) {
            'super_admin' => self::SUPER_ADMIN_WIDGETS,
            'design_only' => self::DESIGN_ONLY_WIDGETS,
            // Everyone else keeps exactly what they had: the panel's
            // registered and discovered widgets, in their $sort order, each
            // still filtered by its own Shield permission.
            default => parent::getWidgets(),
        };
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getColumns(): int|array
    {
        if ($this->currentDashboardVariant() !== 'design_only') {
            return parent::getColumns();
        }

        // Three across once there's room (the summary-cards row and the
        // banner-preview/theme-card row both read this same grid), one
        // column on anything narrower.
        return [
            'default' => 1,
            'lg' => 3,
        ];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->currentDashboardVariant() === 'design_only'
            ? 'Design Overview'
            : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->currentDashboardVariant() === 'design_only'
            ? 'Manage the look of the SIEL CART storefront.'
            : parent::getSubheading();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if ($this->currentDashboardVariant() !== 'design_only') {
            return parent::getHeaderActions();
        }

        return [
            Action::make('viewStorefront')
                ->label('View storefront')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('home'))
                ->openUrlInNewTab(),

            // The button is hidden for an admin who can't create a banner,
            // but the real gate is CreateBanner's own authorizeAccess() --
            // this only saves them a click into a page that would 403.
            Action::make('uploadBanner')
                ->label('Upload banner')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => BannerResource::getUrl('create'))
                ->visible(fn (): bool => BannerResource::canCreate()),
        ];
    }
}
