<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only list of reports made against this customer's reviews. */
class ReportsReceivedRelationManager extends RelationManager
{
    protected static string $relationship = 'reportsReceived';

    protected static ?string $title = 'Reports against customer';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['reporter', 'review.product']))
            ->recordTitleAttribute('reason')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reporter.name')
                    ->label('Reported by'),
                ...self::sharedColumns(),
            ])
            ->recordUrl(fn (Report $record): string => ReportResource::getUrl('edit', ['record' => $record]));
    }

    /** Columns both report tabs show, after their "other party" column. */
    public static function sharedColumns(): array
    {
        return [
            TextColumn::make('reason')
                ->limit(30),
            TextColumn::make('review.product.name')
                ->label('Product')
                ->placeholder('Review removed')
                ->limit(25),
            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'reviewed' => 'success',
                    default => 'gray',
                }),
            TextColumn::make('created_at')
                ->label('Filed')
                ->date('M d, Y')
                ->sortable(),
        ];
    }
}
