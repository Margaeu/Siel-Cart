<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail over Spatie's own activity_log table.
 *
 * The log is only worth anything if nobody can quietly rewrite it, so this
 * resource does not lean on Shield: a generated `Activity` policy (or a role
 * someone ticks a box on) must never be able to grant edit or delete here.
 * Access is decided entirely in this class — super admins may list and view,
 * and every mutating ability is refused for everyone.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $recordTitleAttribute = 'description';

    // Descriptions are mostly bare event names ("updated"), so global search
    // results would be a wall of identical rows. The table search covers it.
    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Admin Activity Logs';

    protected static ?string $modelLabel = 'admin activity';

    protected static ?string $pluralModelLabel = 'admin activity logs';

    protected static ?string $slug = 'admin-activity-logs';

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    /**
     * Newest-first ordering is the table's default sort rather than an
     * orderBy here: Filament appends the user's chosen sort after anything
     * already on the query, so a hard-coded order would override it.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }

    // --- Authorization ---------------------------------------------------

    public static function isSuperAdmin(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasRole('super_admin');
    }

    /**
     * Every page, action, and can*() check funnels through here, so this is
     * what actually enforces "super admins may look, nobody may touch".
     */
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (! in_array($action, ['viewAny', 'view'], true)) {
            return Response::deny('Activity logs are read-only.');
        }

        return static::isSuperAdmin()
            ? Response::allow()
            : Response::deny('Only super admins can view activity logs.');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }
}
