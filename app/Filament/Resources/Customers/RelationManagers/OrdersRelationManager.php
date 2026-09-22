<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Read-only: orders are managed from OrderResource. This only lists them so a
 * customer's history, including a deleted customer's, is visible in one place.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable(),
                TextColumn::make('or_number')
                    ->label('OR Number'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): array|string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'ready_for_pickup' => Color::Purple,
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                TextColumn::make('total')
                    ->money('PHP'),
                TextColumn::make('created_at')
                    ->label('Order date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }
}
