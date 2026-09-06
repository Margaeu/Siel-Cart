<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Customers\CustomerResource;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query())
            ->columns([
                TextColumn::make('order_number')
                    ->weight('bold')
                    ->url(fn ($record) => OrderResource::getUrl('edit', [$record])),

                TextColumn::make('customer.name')
                    ->url(fn ($record) => $record->customer ? CustomerResource::getUrl('edit', [$record->customer]) : null),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'          => 'warning',
                        'processing'       => 'info',
                        'ready_for_pickup' => 'primary',
                        'completed'        => 'success',
                        'return_requested' => 'warning',
                        'return_completed' => 'success',
                        'cancelled'        => 'danger',
                        default            => 'gray',
                    }),

                TextColumn::make('total')
                    ->money('PHP')
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Ordered')
                    ->since(),
            ])
            ->heading('Latest Orders')
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}