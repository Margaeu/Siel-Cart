<?php

namespace App\Filament\Resources\ActivityLogs\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Support\ActivityLogPresenter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

/**
 * Today's totals above the Activity Logs timeline.
 *
 * Lives in the resource directory rather than app/Filament/Widgets so the
 * panel does not also discover it onto the dashboard, where every admin role
 * would see it.
 */
class ActivityLogStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    // One small aggregate query; not worth a second round trip and a loading
    // placeholder above the timeline.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return ActivityLogResource::canViewAny();
    }

    protected function getColumns(): array
    {
        return [
            'default' => 1,
            '@sm' => 2,
            // Four across only once each card has room for a two-line label.
            '@4xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $counts = $this->todaysCounts();

        return [
            Stat::make('Activities today', number_format($counts['total']))
                ->description('Everything recorded since midnight')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray'),

            Stat::make('Successful logins today', number_format($counts['logins']))
                ->description('Admin panel sign-ins')
                ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
                ->color('success'),

            Stat::make('Failed login attempts today', number_format($counts['failed']))
                ->description($counts['throttled'] > 0
                    ? number_format($counts['throttled']).' blocked by the lockout'
                    : 'Wrong email or password')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($counts['failed'] > 0 ? 'danger' : 'gray'),

            Stat::make('Record changes today', number_format($counts['crud']))
                ->description('Created, updated, deleted, or restored')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('warning'),
        ];
    }

    /**
     * One query for all four numbers. CASE/SUM is plain SQL, so this runs the
     * same on MySQL and on the SQLite test database. "Today" is midnight to
     * midnight in the app timezone (Asia/Manila), which is also the timezone
     * the created_at values are written in.
     *
     * @return array{total: int, logins: int, failed: int, throttled: int, crud: int}
     */
    private function todaysCounts(): array
    {
        $failedPlaceholders = implode(', ', array_fill(0, count(ActivityLogPresenter::LOGIN_FAILURE_EVENTS), '?'));
        $crudPlaceholders = implode(', ', array_fill(0, count(ActivityLogPresenter::CRUD_EVENTS), '?'));

        $row = Activity::query()
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN event = ? THEN 1 ELSE 0 END) AS logins', ['login'])
            ->selectRaw("SUM(CASE WHEN event IN ({$failedPlaceholders}) THEN 1 ELSE 0 END) AS failed", ActivityLogPresenter::LOGIN_FAILURE_EVENTS)
            ->selectRaw('SUM(CASE WHEN event = ? THEN 1 ELSE 0 END) AS throttled', ['login_throttled'])
            ->selectRaw("SUM(CASE WHEN event IN ({$crudPlaceholders}) THEN 1 ELSE 0 END) AS crud", ActivityLogPresenter::CRUD_EVENTS)
            ->toBase()
            ->first();

        // SUM() over zero rows is NULL, not 0.
        return [
            'total' => (int) ($row->total ?? 0),
            'logins' => (int) ($row->logins ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'throttled' => (int) ($row->throttled ?? 0),
            'crud' => (int) ($row->crud ?? 0),
        ];
    }
}
