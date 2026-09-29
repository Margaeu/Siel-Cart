<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\Schemas\ReportForm;
use App\Filament\Resources\Reports\Schemas\ReportInfolist;
use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\Report;
use App\Support\AdminNavigationBadges;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Management';

    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return ReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportsTable::configure($table);
    }

    public static function hideReviewAction(): Action
    {
        return Action::make('hideReview')
            ->label('Hide Reported Review')
            ->icon('heroicon-o-eye-slash')
            ->color('warning')
            ->authorize(fn (Report $record): bool => self::canEdit($record)
                && $record->review !== null
                && auth()->user()->can('update', $record->review))
            ->visible(fn (Report $record): bool => (bool) $record->review?->is_approved)
            ->requiresConfirmation()
            ->modalHeading('Hide the reported review?')
            ->modalDescription('This hides the review from the product page and excludes it from the average rating. The report will be marked as reviewed. You can show the review again from Reviews.')
            ->action(function (Report $record): void {
                DB::transaction(function () use ($record): void {
                    $record->review()->update(['is_approved' => false]);
                    $record->update(['status' => 'reviewed']);
                });

                Notification::make()
                    ->title('Review hidden')
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(self::getUrl('index'));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }

    /**
     * Surface the pending moderation queue in the sidebar, same pattern as Reviews.
     */
    public static function getNavigationBadge(): ?string
    {
        return app(AdminNavigationBadges::class)->pending(Report::class);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
