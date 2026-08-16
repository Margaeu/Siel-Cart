<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
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
                    ->url(fn ($record) => OrderResource::getUrl('edit',[$record])),

                TextColumn::make('customer.name')
                    ->url(fn ($record) => CustomerResource::getUrl('edit',[$record->customer])),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                    }),

                TextColumn::make('total')
                    ->money('USD')
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
