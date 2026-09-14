<?php

namespace App\Filament\Resources\ReturnRefunds\Schemas;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use App\Models\OrderItemResolution;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

/**
 * One recorded refund or exchange, read back in full. Older records can lack
 * the details recorded since -- the item released in error, or the admin who
 * has since been removed -- so every such entry has a placeholder.
 */
class ReturnRefundInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Outcome')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->schema([
                    TextEntry::make('type')
                        ->label('Outcome')
                        ->badge(),

                    TextEntry::make('reason')
                        ->label('Reason'),

                    TextEntry::make('quantity')
                        ->label('Units'),

                    TextEntry::make('refund_amount')
                        ->label('Refund amount')
                        ->money('PHP')
                        ->weight(FontWeight::Bold)
                        ->visible(fn (OrderItemResolution $record): bool => $record->type === OrderItemResolutionType::Refund),

                    TextEntry::make('processed_at')
                        ->label('Processed date')
                        ->date('M d, Y'),

                    TextEntry::make('processedBy.name')
                        ->label('Processed by')
                        ->placeholder('Admin account removed'),

                    TextEntry::make('created_at')
                        ->label('Recorded at')
                        ->dateTime('M d, Y - h:i A'),

                    TextEntry::make('notes')
                        ->label('Notes')
                        ->placeholder('No notes')
                        ->columnSpanFull(),
                ]),

            Section::make('Order item')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->schema([
                    TextEntry::make('orderItem.order.order_number')
                        ->label('Order number')
                        ->url(fn (OrderItemResolution $record): ?string => ReturnRefundResource::getOrderUrl($record))
                        ->color('primary'),

                    TextEntry::make('orderItem.order.customer.name')
                        ->label('Customer')
                        ->placeholder('Customer removed'),

                    TextEntry::make('orderItem.product_name')
                        ->label('Product'),

                    TextEntry::make('orderItem.variant_name')
                        ->label('Variant')
                        ->placeholder('—'),

                    TextEntry::make('orderItem.product_sku')
                        ->label('SKU')
                        ->placeholder('—'),

                    TextEntry::make('orderItem.price')
                        ->label('Unit price')
                        ->money('PHP'),

                    TextEntry::make('orderItem.quantity')
                        ->label('Units ordered'),
                ]),

            Section::make('Exchange')
                ->description(fn (OrderItemResolution $record): string => static::isSellerError($record)
                    ? 'UBAP released the wrong item, and the customer was given the item they ordered.'
                    : 'The customer was given another unit of the item they ordered.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 3,
                ])
                ->visible(fn (OrderItemResolution $record): bool => $record->type === OrderItemResolutionType::Exchange)
                ->schema([
                    TextEntry::make('replacement')
                        ->label('Replacement given')
                        ->state(fn (OrderItemResolution $record): ?string => collect([
                            $record->replacement_product_name,
                            $record->replacement_variant_name,
                        ])->filter()->implode(' — ') ?: null)
                        ->placeholder('Not recorded'),

                    // Only seller error has an item released in error to show.
                    TextEntry::make('incorrect_item_label')
                        ->label('Item released in error')
                        ->placeholder('Not recorded')
                        ->visible(fn (OrderItemResolution $record): bool => static::isSellerError($record)),

                    TextEntry::make('incorrect_item_condition')
                        ->label('Condition when returned')
                        ->placeholder('Not recorded')
                        ->visible(fn (OrderItemResolution $record): bool => static::isSellerError($record))
                        ->helperText(fn (OrderItemResolution $record): ?string => match ($record->incorrect_item_condition?->isSellable()) {
                            true => 'Back on the shelf, so its stock was not changed.',
                            false => 'Written off that item\'s stock.',
                            null => null,
                        }),
                ]),
        ]);
    }

    private static function isSellerError(OrderItemResolution $record): bool
    {
        return $record->reason === OrderItemResolutionReason::SellerError;
    }
}
