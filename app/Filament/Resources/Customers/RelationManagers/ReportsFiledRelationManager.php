<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only list of reports this customer filed; handled in ReportResource. */
class ReportsFiledRelationManager extends RelationManager
{
    protected static string $relationship = 'reportsFiled';

    protected static ?string $title = 'Reports filed';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['reportedCustomer', 'review.product']))
            ->recordTitleAttribute('reason')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reportedCustomer.name')
                    ->label('Reported customer'),
                ...ReportsReceivedRelationManager::sharedColumns(),
            ])
            ->recordUrl(fn (Report $record): string => ReportResource::getUrl('edit', ['record' => $record]));
    }
}
