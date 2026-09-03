<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderReadyForPickupMail;
use App\Mail\OrderCompletedMail;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TimePicker;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('claimant_name')
                    ->label('Claimed By')
                    ->placeholder('Name')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Amount')
                    ->money('PHP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
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
                    })
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export to Excel')
                    ->exports([
                        ExcelExport::make('orders_report')
                            ->fromTable(),
                    ]),
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
                        $record->update(['status' => 'processing']);
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
                            ->default(fn () => 'CLM-' . strtoupper(Str::random(6))),
                        DatePicker::make('pickup_date')
                            ->required(),
                        TimePicker::make('pickup_slot')
                            ->label('Pickup Time')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update([
                            'status'       => 'ready_for_pickup',
                            'claim_number' => $data['claim_number'],
                            'pickup_date'  => $data['pickup_date'],
                            'pickup_slot'  => $data['pickup_slot'],
                        ]);
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
                            ->placeholder('e.g. 09171234567')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update([
                            'status'               => 'completed',
                            'payment_status'       => 'paid',
                            'paid_at'              => now(),
                            'completed_at'         => now(),
                            'claimant_name'  => $data['claimant_name'],
                            'claimant_phone' => $data['claimant_phone'],
                        ]);

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
                    ExportBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}