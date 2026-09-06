<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Status & Collection')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                Select::make('status')
                    ->label('Order Status')
                    ->options([
                        'pending'          => 'Pending',
                        'processing'       => 'Processing',
                        'ready_for_pickup' => 'Ready for pickup',
                        'completed'        => 'Completed',
                        'return_requested' => 'Return Requested',
                        'return_completed' => 'Return Completed',
                        'cancelled'        => 'Cancelled',
                    ])
                    ->native(false)
                    ->default('pending')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    
                        Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                                'failed' => 'Failed',
                            ])
                            ->native(false)
                            ->required()
                            ->default('pending'),

                        TextInput::make('claimant_name')
                            ->label('Claimed By (Name)')
                            ->placeholder('Name of person receiving order')
                            ->default(null),

                        TextInput::make('claimant_phone')
                            ->label('Claimant Contact Number')
                            ->placeholder('Phone number of person receiving order')
                            ->default(null),

                        DatePicker::make('pickup_date')
                            ->label('Scheduled Pickup Date')
                            ->nullable(),

                        TextInput::make('pickup_slot')
                            ->label('Scheduled Pickup Time Interval')
                            ->placeholder('e.g. 8:00 AM - 5:00 PM')
                            ->regex('/^(?:0?[1-9]|1[0-2]):[0-5][0-9] ?(?:AM|PM)\s*-\s*(?:0?[1-9]|1[0-2]):[0-5][0-9] ?(?:AM|PM)$/i')
                            ->validationMessages([
                                'regex' => 'Enter a time interval such as 8:00 AM - 5:00 PM.',
                            ])
                            ->nullable(),

                        Textarea::make('admin_notes')
                            ->default(null),

                    ]),
            ]);
    }
}
