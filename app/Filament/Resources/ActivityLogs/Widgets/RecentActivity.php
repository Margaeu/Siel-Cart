<?php

namespace App\Filament\Resources\ActivityLogs\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\UserResource;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * The newest handful of audit entries, for the super admin's dashboard.
 *
 * Deliberately not the Activity Logs table: the dashboard shows a short,
 * fixed, newest-first list with no search, filters, or pagination, and the
 * "View all activity logs" shortcut hands the admin over to the resource for
 * any of that. Every word it prints still comes from
 * App\Support\ActivityLogPresenter, so the dashboard and the resource can
 * never disagree about what an event means or which values are safe to show.
 *
 * Like ActivityLogStats it lives in the resource directory rather than
 * app/Filament/Widgets, so the panel's discoverWidgets() does not also drop it
 * onto every admin role's dashboard — App\Filament\Pages\Dashboard picks it
 * explicitly for super admins. canView() below is the actual guard: hiding a
 * widget from a dashboard is a layout decision, not authorization, and a
 * widget is its own Livewire component that can be reached directly.
 */
class RecentActivity extends Widget
{
    /**
     * How many entries the dashboard shows. Kept small on purpose: this is a
     * glance at the trail, and the query is bounded by it.
     */
    public const LIMIT = 10;

    protected string $view = 'filament.activity-logs.recent-activity';

    protected int|string|array $columnSpan = 'full';

    // Under ActivityLogStats (sort 0 by default on StatsOverviewWidget's own
    // slot) so today's totals read first, then what actually happened.
    protected static ?int $sort = 1;

    // One bounded query; not worth a second round trip and a loading skeleton.
    protected static bool $isLazy = false;

    /**
     * The same super-admin-only rule the resource enforces, reused rather than
     * restated, so the widget cannot drift from the page it links to.
     */
    public static function canView(): bool
    {
        return ActivityLogResource::canViewAny();
    }

    /**
     * Newest first, with `id` breaking ties: several rows can share a
     * created_at to the second (a login and the model write that follows it),
     * and without the tiebreaker their order would be whatever the database
     * happened to return.
     *
     * causer and subject are eager-loaded because the presenter reads a name
     * off each of them — without this the list would be 10 rows and 21
     * queries.
     *
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * The links under the list. "View all activity logs" is always there (the
     * widget is only visible to someone who may open it); Users and Roles are
     * each shown only to an admin the resource itself would let in, so the
     * shortcut can never offer a page that then refuses them.
     *
     * @return list<array{label: string, url: string, icon: Heroicon}>
     */
    public function getShortcuts(): array
    {
        $shortcuts = [[
            'label' => 'View all activity logs',
            'url' => ActivityLogResource::getUrl('index'),
            'icon' => Heroicon::OutlinedClipboardDocumentList,
        ]];

        if (UserResource::canViewAny()) {
            $shortcuts[] = [
                'label' => 'Users',
                'url' => UserResource::getUrl('index'),
                'icon' => Heroicon::OutlinedUsers,
            ];
        }

        if (RoleResource::canViewAny()) {
            $shortcuts[] = [
                'label' => 'Roles',
                'url' => RoleResource::getUrl('index'),
                'icon' => Heroicon::OutlinedShieldCheck,
            ];
        }

        return $shortcuts;
    }
}
