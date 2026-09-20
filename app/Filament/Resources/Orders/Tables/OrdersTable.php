<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Mail\OrderCompletedMail;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Amount')
                    ->money('PHP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('or_number')
                    ->label('OR Number')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): array | string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'ready_for_pickup' => Color::Purple,
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
                    ->state(fn (Order $record) => $record->created_at)
                    ->date('M d, Y')
                    ->sortable(['created_at']),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Collected / Cancelled')
                    ->state(fn (Order $record) => $record->status === 'cancelled'
                        ? $record->cancelled_at
                        : $record->completed_at)
                    ->dateTime('M d, Y')
                    ->placeholder(fn (Order $record): string => $record->status === 'cancelled'
                        ? 'Cancellation date unavailable'
                        : 'Not yet collected'),
            ])

            ->filters([
                TrashedFilter::make(),
            ])

            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                // 1. Pending -> Processing
                Action::make('mark_processing')
                    ->label('Mark Processing')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Order $record) {
                        $record->updateStatus('processing', 'Order is being processed.',auth()->id(),);
                        Mail::to($record->customer->email)->send(new OrderProcessingMail($record));
                    })
                    ->visible(fn (Order $record) => strtolower($record->status) === 'pending'),

                // 2. Processing -> Ready for Pickup
                Action::make('mark_ready_for_pickup')
                    ->label('Ready for Pickup')
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        TextInput::make('claim_number')
                            ->required()
                            ->default(fn () => 'CLM-'.strtoupper(Str::random(6))),
                        DatePicker::make('pickup_date')
                            ->required(),
                        TimePicker::make('pickup_start_time')
                            ->label('Pickup Time From')
                            ->seconds(false)
                            ->default('08:00')
                            ->required(),
                        TimePicker::make('pickup_end_time')
                            ->label('Pickup Time Until')
                            ->seconds(false)
                            ->default('17:00')
                            ->after('pickup_start_time')
                            ->validationMessages([
                                'after' => 'The pickup end time must be later than the start time.',
                            ])
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->updateStatus( 'ready_for_pickup','Order is ready for pickup.', auth()->id(),
                            [
                                'claim_number' => $data['claim_number'],
                                'pickup_date'  => $data['pickup_date'],
                                'pickup_slot'  => Carbon::parse($data['pickup_start_time'])->format('g:i A')
                                    . ' - '
                                    . Carbon::parse($data['pickup_end_time'])->format('g:i A'),
                            ],
                        );
                        Mail::to($record->customer->email)->send(new OrderReadyForPickupMail($record));
                    })
                    ->visible(fn (Order $record) => strtolower($record->status) === 'processing'),

                // 3. Complete Pickup & Record Claimant
                Action::make('mark_completed')
                    ->label('Complete Pickup')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        TextInput::make('claimant_name')
                            ->label('Name of Person Receiving/Claiming Order')
                            ->placeholder('e.g. Juan Dela Cruz')
                            ->required(),
                        TextInput::make('claimant_phone')
                            ->label('Contact Phone Number of Receiver')
                            ->placeholder('e.g. 0917123459')
                            ->required(),
                        TextInput::make('or_number')
                            ->label('Official Receipt Number')
                            ->placeholder('e.g. or-2345')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                            $record->updateStatus('completed', 'Order was collected and completed.', auth()->id(),
                                [
                                    'payment_status' => 'paid',
                                    'completed_at'   => now(),
                                    'claimant_name'  => $data['claimant_name'],
                                    'claimant_phone' => $data['claimant_phone'],
                                    'or_number' => $data['or_number'],
                                ],
                            );

                        Mail::to($record->customer->email)->send(new OrderCompletedMail($record));

                        Notification::make()
                            ->title('Order Marked as Completed')
                            ->body('Claimant details recorded and email notification sent.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Order $record) => in_array(strtolower($record->status), ['ready_for_pickup', 'ready for pickup'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
