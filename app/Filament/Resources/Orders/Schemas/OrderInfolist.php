<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Information')
                ->columns(3)
                ->schema([
                    TextEntry::make('order_number')->label('Order #'),

                    TextEntry::make('created_at')
                        ->label('Date Placed')
                        ->dateTime('M d, Y h:i A'),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(
                            fn ($state) => Str::headline($state)
                        ),

                    TextEntry::make('payment_method')
                        ->formatStateUsing(
                            fn ($state) => Str::headline($state)
                        ),

                    TextEntry::make('payment_status')
                        ->badge()
                        ->formatStateUsing(
                            fn ($state) => Str::headline($state)
                        ),
                ]),

            Section::make('Customer Information')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer.name')
                        ->label('Customer'),

                    TextEntry::make('customer.email')
                        ->label('Email'),

                    TextEntry::make('customer.phone')
                        ->label('Phone')
                        ->placeholder('Not provided'),
                ]),

            Grid::make([
                'default' => 1,
                'xl' => 12,
            ])
                ->columnSpanFull()
                ->schema([
                    Section::make('Ordered Products')
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 7,
                        ])
                        ->schema([
                            RepeatableEntry::make('items')
                                ->hiddenLabel()
                                ->table([
                                    TableColumn::make('Product'),
                                    TableColumn::make('Variation'),
                                    TableColumn::make('SKU'),
                                    TableColumn::make('Unit Price'),
                                    TableColumn::make('Quantity'),
                                    TableColumn::make('Subtotal'),
                                ])
                                ->schema([
                                    TextEntry::make('product_name'),
                                    TextEntry::make('variant_name')
                                        ->placeholder('—'),
                                    TextEntry::make('product_sku'),
                                    TextEntry::make('price')
                                        ->money('PHP'),
                                    TextEntry::make('quantity'),
                                    TextEntry::make('subtotal')
                                        ->money('PHP'),
                                ]),
                        ]),

                    Group::make([
                        Section::make('Order Totals')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Merchandise Subtotal')
                                    ->money('PHP'),

                                TextEntry::make('total')
                                    ->label('Order Total')
                                    ->money('PHP'),
                            ]),

                        Section::make('Pickup and Claimant Information')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('pickup_location')
                                    ->label('Pickup Location')
                                    ->placeholder('UBAP Office'),

                                TextEntry::make('pickup_date')
                                    ->label('Pickup Date')
                                    ->date('M d, Y')
                                    ->placeholder('Not scheduled'),

                                TextEntry::make('pickup_slot')
                                    ->label('Pickup Time')
                                    ->time('h:i A')
                                    ->placeholder('Not scheduled'),

                                TextEntry::make('claim_number')
                                    ->label('Claim Number')
                                    ->placeholder('Not issued'),

                                TextEntry::make('claimant_name')
                                    ->label('Claimed By')
                                    ->placeholder('Not designated'),

                                TextEntry::make('claimant_phone')
                                    ->label('Claimant Phone')
                                    ->placeholder('Not provided'),

                                TextEntry::make('admin_notes')
                                    ->label('Admin Notes')
                                    ->placeholder('No notes')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Status History')
                            ->schema([
                                RepeatableEntry::make('statusHistories')
                                    ->hiddenLabel()
                                    ->table([
                                        TableColumn::make('Date'),
                                        TableColumn::make('Status'),
                                        TableColumn::make('Notes'),
                                    ])
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->dateTime('M d, Y h:i A'),

                                        TextEntry::make('status')
                                            ->formatStateUsing(
                                                fn ($state) => Str::headline($state)
                                            ),

                                        TextEntry::make('notes')
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ])
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 5,
                        ]),
                ]),
        ]);
    }
}