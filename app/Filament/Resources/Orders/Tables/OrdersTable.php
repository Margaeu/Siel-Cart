<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Customer;
use App\Models\Order;
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
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Every poll is a full Livewire request that re-runs this table's
            // query and re-renders it. Production is a single shared vCPU
            // (B1) with the database in another region, so at 10s an open tab
            // queued background work in front of the admin's own clicks.
            // New orders are still flagged within a minute by the sidebar
            // badge poller (NavigationBadgePoller), which runs on the same
            // interval.
            ->poll('60s')
            ->recordClasses(fn (Order $record): ?string => $record->canBeCancelledForNoShow()
                ? 'fi-order-pickup-overdue'
                : null)
            // Newest first, so an admin sees a record they just created or changed
            // at the top and can confirm the change landed.
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    // `name` is an accessor, not a column: the default search
                    // queried customers.name and threw, which the panel shows
                    // as "Error while loading page". The relation is
                    // withTrashed(), so a deleted customer's orders stay findable.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'customer',
                        fn (Builder $q) => $q->nameLike($search),
                    ))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Customer::withTrashed()->select('first_name')->whereColumn('customers.id', 'orders.customer_id')->limit(1),
                        $direction,
                    )),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Amount')
                    ->money('PHP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('or_number')
                    ->label('OR Number')
                    ->placeholder('Not yet issued')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): array|string => match ($state) {
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
                        $record->updateStatus('processing', 'Order is being processed.', auth()->id());

                        OrderResource::notifyCustomerByMail(
                            $record,
                            new OrderProcessingMail($record),
                            'Order marked as processing',
                            'The order is now being processed.',
                        );
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
                        $record->updateStatus('ready_for_pickup', 'Order is ready for pickup.', auth()->id(),
                            [
                                'claim_number' => $data['claim_number'],
                                'pickup_date' => $data['pickup_date'],
                                'pickup_slot' => Order::pickupSlotFrom($data['pickup_start_time'], $data['pickup_end_time']),
                            ],
                        );
                        OrderResource::notifyCustomerByMail(
                            $record,
                            new OrderReadyForPickupMail($record),
                            'Order marked as ready for pickup',
                            'Claim number '.$record->claim_number.' issued.',
                        );
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
                        // Optional on purpose: the buyer collecting their own
                        // order is the normal case, and their number is already
                        // on the customer record. Only a third-party claimant
                        // adds a contact number the shop does not already hold.
                        TextInput::make('claimant_phone')
                            ->label('Contact Phone Number of Receiver')
                            ->helperText('Only needed when someone other than the customer who ordered is collecting.'),
                        TextInput::make('or_number')
                            ->label('Official Receipt Number')
                            ->placeholder('e.g. 1234567')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->updateStatus('completed', 'Order was collected and completed.', auth()->id(),
                            [
                                'payment_status' => 'paid',
                                'completed_at' => now(),
                                'claimant_name' => $data['claimant_name'],
                                // Blank stays NULL so every reader can tell
                                // "no separate claimant number" from a real one.
                                'claimant_phone' => $data['claimant_phone'] ?: null,
                                'or_number' => $data['or_number'],
                            ],
                        );

                        OrderResource::notifyCustomerByMail(
                            $record,
                            new OrderCompletedMail($record),
                            'Order marked as completed',
                            'Claimant details recorded.',
                        );
                    })
                    ->visible(fn (Order $record) => in_array(strtolower($record->status), ['ready_for_pickup', 'ready for pickup'])),

                // 4. Send the current status email again, for a send that
                //    failed or a customer who says it never arrived.
                OrderResource::resendStatusEmailAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Same warnings as EditOrder's header actions: deleting is
                    // not cancelling, and force-deleting erases the order.
                    DeleteBulkAction::make()
                        ->modalHeading(fn (Collection $records): string => 'Delete '.$records->count().' '.str('order')->plural($records->count()).'?')
                        ->modalDescription(fn (Collection $records): string => 'Orders '.$records->pluck('order_number')->implode(', ').' will be hidden from the order lists and from the customers\' order histories, and can be restored later (filter by "Deleted records"). Deleting does not cancel them: reserved items are not returned to stock and no one is emailed.'),
                    ForceDeleteBulkAction::make()
                        ->modalHeading(fn (Collection $records): string => 'Permanently delete '.$records->count().' '.str('order')->plural($records->count()).'?')
                        ->modalDescription(fn (Collection $records): string => 'Orders '.$records->pluck('order_number')->implode(', ').' and their items and status history will be erased for good and drop out of sales figures. This cannot be undone.'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
