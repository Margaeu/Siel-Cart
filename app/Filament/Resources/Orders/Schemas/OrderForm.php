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
                Section::make('Order status')
                    ->description('Review the fulfilment and payment state for this order.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->label('Order status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'ready_for_pickup' => 'Ready for pickup',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->native(false)
                            ->default('pending')
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        Select::make('payment_status')
                            ->label('Payment status')
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
                    ]),

                Section::make('Pickup and claimant')
                    ->description('Schedule collection and record who will receive the order.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('claimant_name')
                            ->label('Claimed by')
                            ->placeholder('Name of person receiving order')
                            ->default(null),

                        // Blank is normal: it means the customer who ordered
                        // collected it themselves, so their own number applies.
                        TextInput::make('claimant_phone')
                            ->label('Claimant contact number')
                            ->placeholder('Leave blank if the customer is collecting')
                            ->helperText('Only needed when someone other than the customer who ordered is collecting.')
                            ->default(null),

                        TextInput::make('or_number')
                            ->label('Official Receipt Number')
                            ->placeholder('e.g. or-2345'),

                        // Read-only here. The schedule is set by "Ready for
                        // Pickup" and moved by "Reschedule Pickup", which keep
                        // the original schedule and email the customer. Editing
                        // it in this form silently did neither.
                        DatePicker::make('pickup_date')
                            ->label('Scheduled pickup date')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(fn ($record): ?string => $record?->canBeRescheduled()
                                ? 'Use "Reschedule Pickup" above to change the schedule.'
                                : null),

                        TextInput::make('pickup_slot')
                            ->label('Scheduled pickup time')
                            ->placeholder('Not yet scheduled')
                            ->disabled()
                            ->dehydrated(false),

                    ]),

                Section::make('Internal notes')
                    ->description('Add context for administrators. Customers do not see these notes.')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(4)
                            ->default(null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
