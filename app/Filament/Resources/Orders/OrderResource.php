<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Mail\OrderRescheduledMail;
use App\Models\Order;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static string|UnitEnum|null $navigationGroup = 'Shop Management';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    // "New" means pending: an order sits there from checkout until an admin
    // marks it processing, so the badge clears itself as orders are picked
    // up rather than needing a separate "seen" flag. Trashed orders are
    // excluded by the model's soft-delete scope.
    public static function getNavigationBadge(): ?string
    {
        $pending = Order::ofStatus('pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New orders waiting to be processed';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Move a ready order's pickup to another day, usually because the
     * customer contacted UBAP to say they cannot come on the scheduled one.
     * Shared by OrdersTable's row action and the view/edit page headers so
     * all three validate, record, and email the customer identically.
     *
     * Order::reschedulePickup() does the write; this only collects the new
     * schedule and sends OrderRescheduledMail once the write has committed.
     */
    public static function reschedulePickupAction(): Action
    {
        return Action::make('reschedule_pickup')
            ->label('Reschedule Pickup')
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->authorize(fn (Order $record): bool => self::canEdit($record))
            ->visible(fn (Order $record): bool => $record->canBeRescheduled())
            ->modalHeading('Reschedule pickup')
            ->modalDescription(fn (Order $record): string => 'Currently scheduled for '
                .$record->pickup_date?->format('M d, Y').' ('.$record->pickup_slot.'). '
                .'The customer keeps the same claim number and is emailed the new schedule.')
            ->modalSubmitActionLabel('Reschedule and notify customer')
            ->fillForm(function (Order $record): array {
                preg_match_all('/(?:0?[1-9]|1[0-2]):[0-5][0-9]\s*(?:AM|PM)/i', (string) $record->pickup_slot, $matches);
                $times = $matches[0];

                return [
                    'pickup_start_time' => filled($times) ? Carbon::parse(reset($times))->format('H:i') : '08:00',
                    'pickup_end_time' => count($times) > 1 ? Carbon::parse(end($times))->format('H:i') : '17:00',
                ];
            })
            ->schema([
                DatePicker::make('pickup_date')
                    ->label('New pickup date')
                    ->minDate(today())
                    ->required(),
                TimePicker::make('pickup_start_time')
                    ->label('Pickup Time From')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('pickup_end_time')
                    ->label('Pickup Time Until')
                    ->seconds(false)
                    ->after('pickup_start_time')
                    ->validationMessages([
                        'after' => 'The pickup end time must be later than the start time.',
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason (internal)')
                    ->placeholder('e.g. Customer called, unable to come on the scheduled day')
                    ->helperText('Saved to the order history. The customer does not see this.')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (Order $record, array $data, Action $action): void {
                // Re-read first, so the "previous schedule" in the email is the
                // one currently stored, not whatever this page loaded earlier.
                $record->refresh();
                $previousDate = $record->pickup_date;
                $previousSlot = $record->pickup_slot;

                try {
                    $order = $record->reschedulePickup(
                        $data['pickup_date'],
                        Order::pickupSlotFrom($data['pickup_start_time'], $data['pickup_end_time']),
                        $data['reason'] ?? null,
                        auth()->id(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Pickup not rescheduled')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                if (! $order) {
                    Notification::make()
                        ->title('Pickup not rescheduled')
                        ->body('Only an order that is ready for pickup can be rescheduled.')
                        ->danger()
                        ->send();

                    return;
                }

                Mail::to($order->customer->email)
                    ->send(new OrderRescheduledMail($order, $previousDate, $previousSlot));

                $record->refresh();

                Notification::make()
                    ->title('Pickup rescheduled')
                    ->body('New schedule: '.$order->pickup_date->format('M d, Y').' ('.$order->pickup_slot.'). The customer was notified by email.')
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
