<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\User;
use App\Support\ActivityLogPresenter;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity Logs as a timeline rather than a grid.
 *
 * Wrapping the single column in a Stack switches Filament into its "content"
 * layout: no header row, one block per record. Search, filters, sorting, and
 * pagination are still Filament's own, so only the row itself is custom.
 */
class ActivityLogsTable
{
    /** Filter value for activity nobody signed in caused (the app itself, or a guest). */
    private const NO_CAUSER = '__none';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    // Named after created_at so the toolbar's sort control
                    // reads "Date" and sorts on the indexed timestamp.
                    ViewColumn::make('created_at')
                        ->label('Date')
                        ->view('filament.activity-logs.timeline-entry')
                        ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('created_at', $direction)
                            ->orderBy('id', $direction))
                        ->searchable(query: fn (Builder $query, string $search): Builder => static::search($query, $search)),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search description, event, log, admin, or record ID')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->multiple()
                    ->options(fn (): array => ActivityLogPresenter::eventOptions(
                        static::distinctValues('event'),
                    )),

                SelectFilter::make('log_name')
                    ->label('Log')
                    ->multiple()
                    ->options(fn (): array => collect(static::distinctValues('log_name'))
                        ->mapWithKeys(fn (string $name): array => [$name => str($name)->headline()->toString()])
                        ->all()),

                SelectFilter::make('causer')
                    ->label('Administrator')
                    ->searchable()
                    ->options(fn (): array => [
                        self::NO_CAUSER => 'System / Guest (no signed-in admin)',
                        ...User::query()
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get(['id', 'first_name', 'last_name', 'email'])
                            ->mapWithKeys(fn (User $user): array => [
                                (string) $user->id => trim("{$user->name} ({$user->email})"),
                            ])
                            ->all(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => static::filterByCauser($query, $data['value'] ?? null)),

                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->multiple()
                    ->options(fn (): array => collect(static::distinctValues('subject_type'))
                        ->mapWithKeys(fn (string $type): array => [$type => ActivityLogPresenter::modelLabelFor($type)])
                        ->sort()
                        ->all()),

                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false)
                            ->maxDate(now()),
                        DatePicker::make('until')
                            ->label('Until')
                            ->native(false)
                            ->maxDate(now()),
                    ])
                    // Whole days in the app's timezone (Asia/Manila), compared
                    // as timestamps so the query is the same on MySQL and SQLite.
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From '.Carbon::parse($data['from'])->format('M j, Y'))
                                ->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Until '.Carbon::parse($data['until'])->format('M j, Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(2)
            // A record URL would wrap each entry in an <a>, which swallows the
            // clicks on "Show full value" and stops admins selecting text to
            // copy. The Details action below opens the entry instead.
            ->recordUrl(null)
            ->recordActions([
                ViewAction::make()
                    ->label('Details')
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->toolbarActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('No activity recorded yet')
            ->emptyStateDescription('Activity will appear here after administrators sign in or change tracked records such as products, categories, orders, and users.');
    }

    /**
     * Plain LIKE and equality comparisons only, so search behaves the same on
     * MySQL (local) and SQLite (tests). Property payloads are deliberately not
     * searched: they can hold values this page masks, and a match would reveal
     * them one guess at a time.
     *
     * The term is not LIKE-escaped: SQLite has no default escape character,
     * so "\_" would stop "login_failed" matching there. A stray "_" or "%"
     * only widens the match, which is harmless for a search box.
     */
    private static function search(Builder $query, string $search): Builder
    {
        $search = trim($search);
        $like = '%'.$search.'%';

        return $query->where(function (Builder $query) use ($search, $like): void {
            $query
                ->where('description', 'like', $like)
                ->orWhere('event', 'like', $like)
                ->orWhere('log_name', 'like', $like)
                ->orWhere(function (Builder $query) use ($like): void {
                    $query
                        ->where('causer_type', (new User)->getMorphClass())
                        ->whereIn('causer_id', User::query()
                            ->select('id')
                            ->where(fn (Builder $users): Builder => $users
                                ->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('email', 'like', $like)));
                });

            // "42" or "#42" finds Product #42, Order #42, and activity #42.
            $id = ltrim($search, '#');

            if (ctype_digit($id)) {
                $query
                    ->orWhere('subject_id', (int) $id)
                    ->orWhere('id', (int) $id);
            }
        });
    }

    private static function filterByCauser(Builder $query, ?string $value): Builder
    {
        if (blank($value)) {
            return $query;
        }

        if ($value === self::NO_CAUSER) {
            return $query->whereNull('causer_id');
        }

        return $query
            ->where('causer_type', (new User)->getMorphClass())
            ->where('causer_id', (int) $value);
    }

    /**
     * @return list<string>
     */
    private static function distinctValues(string $column): array
    {
        return Activity::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($value): string => (string) $value)
            ->all();
    }
}
