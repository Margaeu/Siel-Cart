<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Customer;
use App\Models\ReturnRefundResolution;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Str;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order overview')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'xl' => 12,
                ])
                ->schema([
                    Fieldset::make('Order')
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                        ])
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 5,
                        ])
                        ->schema([
                            TextEntry::make('order_number')
                                ->label('Order number'),

                            TextEntry::make('or_number')
                                ->label('OR Number'),

                            TextEntry::make('status')
                                ->label('Order status')
                                ->badge()
                                ->color(fn (string $state): array|string => match ($state) {
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'ready_for_pickup' => Color::Purple,
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn ($state) => Str::headline($state)),

                            TextEntry::make('created_at')
                                ->label('Placed at')
                                ->dateTime('M d, Y - h:i A')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('completed_at')
                                ->label(fn ($record): string => $record->status === 'cancelled'
                                    ? 'Cancelled at'
                                    : 'Collected at')
                                ->state(fn ($record) => $record->status === 'cancelled'
                                    ? $record->cancelled_at
                                    : $record->completed_at)
                                ->dateTime('M d, Y - h:i A')
                                ->placeholder(fn ($record): string => $record->status === 'cancelled'
                                    ? 'Cancellation date unavailable'
                                    : 'Not yet collected')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('payment_method')
                                ->label('Payment method')
                                ->formatStateUsing(fn ($state) => Str::headline($state)),

                            TextEntry::make('payment_status')
                                ->label('Payment status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'pending' => 'warning',
                                    'paid' => 'success',
                                    'cancelled', 'failed' => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn ($state) => Str::headline($state)),
                        ]),

                    Fieldset::make('Customer')
                        ->columns(1)
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 4,
                        ])
                        ->schema([
                            TextEntry::make('customer.name')
                                ->label('Name'),

                            // A deleted customer's stored email is an internal
                            // placeholder and the phone is NULL; show neither.
                            TextEntry::make('customer.email')
                                ->label('Email')
                                ->state(fn ($record) => $record->customer?->trashed()
                                    ? Customer::REMOVED_LABEL
                                    : $record->customer?->email),
                        ]),

                    Fieldset::make('Totals')
                        ->columns(1)
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 3,
                        ])
                        ->schema([
                            TextEntry::make('subtotal')
                                ->label('Merchandise subtotal')
                                ->money('PHP'),

                            TextEntry::make('total')
                                ->label('Order total')
                                ->money('PHP')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('cancellation_reason')
                                ->label('Cancellation reason')
                                ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                                    'change_of_mind' => 'Change of mind',
                                    'incorrect_items' => 'Added wrong item/quantity',
                                    null, '' => null,
                                    default => Str::headline($state),
                                })
                                ->placeholder('Not provided')
                                ->visible(fn ($record): bool => $record->status === 'cancelled'),

                        ]),
                ]),

            Section::make('Ordered products')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Product'),
                            TableColumn::make('Variation'),
                            TableColumn::make('SKU'),
                            TableColumn::make('Unit price'),
                            TableColumn::make('Quantity'),
                            TableColumn::make('Subtotal'),
                            TableColumn::make('Returns'),
                        ])
                        ->schema([
                            TextEntry::make('product_name'),
                            TextEntry::make('variant_name')
                                ->placeholder('—'),
                            TextEntry::make('product_sku')
                                ->placeholder('—'),
                            TextEntry::make('price')
                                ->money('PHP'),
                            TextEntry::make('quantity'),
                            TextEntry::make('subtotal')
                                ->money('PHP'),
                            // Only what happened and when, e.g. "Refunded ×1 · Sep 13, 2026".
                            // The full record is under Returns & Refunds.
                            TextEntry::make('resolutions')
                                ->formatStateUsing(fn (ReturnRefundResolution $state): string => $state->type->getOutcomeLabel()
                                    .' ×'.$state->quantity
                                    .' · '.$state->processed_at->format('M d, Y'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Pickup and claimant information')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ])
                ->schema([
                    TextEntry::make('claim_number')
                        ->label('Claim number')
                        ->placeholder('Not yet issued')
                        ->copyable(fn ($record): bool => filled($record->claim_number))
                        ->copyMessage('Claim number copied')
                        ->size(fn ($record): TextSize => $record->status === 'ready_for_pickup'
                            ? TextSize::Large
                            : TextSize::Medium)
                        ->weight(fn ($record): FontWeight => $record->status === 'ready_for_pickup'
                            ? FontWeight::Bold
                            : FontWeight::Normal)
                        ->color(fn ($record): ?string => $record->status === 'ready_for_pickup'
                            ? 'primary'
                            : null)
                        ->columnSpanFull(),

                    Fieldset::make('Pickup schedule')
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                        ])
                        ->columnSpan([
                            'default' => 1,
                            'lg' => fn ($record): int => $record->status === 'completed' ? 1 : 2,
                        ])
                        ->schema([
                            TextEntry::make('pickup_location')
                                ->label('Location')
                                ->placeholder('UBAP Office')
                                ->columnSpanFull(),

                            TextEntry::make('pickup_date')
                                ->label('Date')
                                ->date('M d, Y')
                                ->placeholder('Not yet scheduled'),

                            TextEntry::make('pickup_slot')
                                ->label('Time interval')
                                ->placeholder('Not yet scheduled'),
                        ]),

                    Fieldset::make('Claimant')
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                        ])
                        ->visible(fn ($record): bool => $record->status === 'completed')
                        ->schema([
                            TextEntry::make('claimant_name')
                                ->label('Name')
                                ->placeholder('Not yet designated'),

                            // Empty is not a gap in the record: no separate
                            // claimant number means the customer who ordered
                            // collected the order themselves.
                            TextEntry::make('claimant_phone')
                                ->label('Phone')
                                ->placeholder('Same as the customer who ordered'),

                            TextEntry::make('or_number')
                                ->label('Official Receipt Number')
                                ->placeholder('Not yet provided'),
                        ]),

                    TextEntry::make('admin_notes')
                        ->label('Admin notes')
                        ->placeholder('No notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
