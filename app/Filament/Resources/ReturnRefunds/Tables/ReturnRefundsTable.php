<?php

namespace App\Filament\Resources\ReturnRefunds\Tables;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use App\Models\OrderItemResolution;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recorded refunds and exchanges, newest first. Read-only: a record is history,
 * so there is nothing here to edit, delete or select in bulk.
 */
class ReturnRefundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('processed_at', 'desc')
            ->columns([
                TextColumn::make('processed_at')
                    ->label('Processed')
                    ->date('M d, Y')
                    ->sortable(),

                // Orders are soft-deleted, and their history is still searchable.
                TextColumn::make('orderItem.order.order_number')
                    ->label('Order #')
                    ->url(fn (OrderItemResolution $record): ?string => ReturnRefundResource::getOrderUrl($record))
                    ->color('primary')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'orderItem.order',
                        fn (Builder $query) => $query->withTrashed()->where('order_number', 'like', "%{$search}%"),
                    )),

                TextColumn::make('orderItem.order.customer.name')
                    ->label('Customer')
                    ->placeholder('Customer removed')
                    // first_name/last_name are the real columns behind the accessor.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'orderItem.order',
                        fn (Builder $query) => $query->withTrashed()->whereHas(
                            'customer',
                            fn (Builder $query) => $query->withTrashed()->where(fn (Builder $query) => $query
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")),
                        ),
                    )),

                TextColumn::make('order_item')
                    ->label('Order item')
                    ->state(fn (OrderItemResolution $record): string => collect([
                        $record->orderItem->product_name,
                        $record->orderItem->variant_name,
                    ])->filter()->implode(' — '))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'orderItem',
                        fn (Builder $query) => $query->where('product_name', 'like', "%{$search}%"),
                    )),

                TextColumn::make('type')
                    ->label('Outcome')
                    ->badge(),

                TextColumn::make('reason')
                    ->label('Reason'),

                TextColumn::make('quantity')
                    ->label('Order Quantity')
                    ->numeric(),

                TextColumn::make('refund_amount')
                    ->label('Refund')
                    ->money('PHP')
                    ->placeholder('—'),

                TextColumn::make('incorrect_item_label')
                    ->label('Released in error')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('incorrect_item_condition')
                    ->label('Returned as')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('processedBy.name')
                    ->label('Processed by')
                    ->placeholder('Admin account removed')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Outcome')
                    ->options(OrderItemResolutionType::class),

                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options(OrderItemResolutionReason::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No refunds or exchanges recorded')
            ->emptyStateDescription('Record one here once UBAP has refunded or exchanged an item from a collected order.');
    }
}
