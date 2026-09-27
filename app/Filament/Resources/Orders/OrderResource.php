<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Mail\OrderCancelledNoShowMail;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Order;
use App\Support\AdminNavigationBadges;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
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
        return app(AdminNavigationBadges::class)->pending(Order::class);
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
     * Send one of the order emails and tell the admin what actually happened.
     *
     * Every status change emails the customer from the action itself, but the
     * panel used to say so only for some of them: an admin who marked an order
     * processing or ready for pickup got no confirmation at all and could not
     * tell whether the customer had been told. Worse, a mail failure threw out
     * of the action *after* the status had already been written and committed,
     * so the admin saw a generic error and could not tell whether the order
     * had moved at all.
     *
     * So the send is wrapped here instead of being repeated at each call site:
     * a success names the address it reached, a failure says plainly that the
     * update was saved but the email was not sent (and stays on screen until
     * it is dismissed, because that is the case an admin has to act on), and
     * the exception is logged rather than swallowed.
     *
     * $detail is the action's own sentence — what the action recorded — and is
     * kept in front of the mail outcome in every branch, so a failed send
     * still tells the admin plainly that the change itself was saved.
     */
    public static function notifyCustomerByMail(
        Order $order,
        Mailable $mailable,
        string $title,
        ?string $detail = null,
    ): void {
        $sentence = fn (string $outcome): string => trim(trim((string) $detail).' '.$outcome);

        $email = $order->customer?->email;

        // Defensive: orders.customer_id is not nullable and the relation is
        // withTrashed(), so even a deleted customer is still reachable. This
        // only fires on an inconsistent record, where saying so beats a fatal
        // error on a null customer *after* the status has been written.
        if (blank($email)) {
            Notification::make()
                ->title($title)
                ->body($sentence('No email was sent: this order has no customer email address on file.'))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        try {
            Mail::to($email)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Order email failed to send.', [
                'order_id' => $order->getKey(),
                'order_number' => $order->order_number,
                'mailable' => $mailable::class,
                'recipient' => $email,
                'exception' => $e,
            ]);

            Notification::make()
                ->title($title)
                ->body($sentence('The email to '.$email.' could not be sent. Use "Resend email" on the order to try again, or contact the customer another way.'))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($title)
            ->body($sentence('Email sent to '.$email.'.'))
            ->success()
            ->send();
    }

    /**
     * The email that describes an order's current state, or null when there
     * is none to repeat.
     *
     * This is what "resend" means for an order: not a stored copy of the last
     * message, but the same mailable the status action would build now, so it
     * carries the current claim number and pickup schedule rather than a
     * stale one.
     */
    public static function statusMailableFor(Order $order): ?Mailable
    {
        return match ($order->status) {
            'processing' => new OrderProcessingMail($order),
            'ready_for_pickup' => new OrderReadyForPickupMail($order),
            'completed' => new OrderCompletedMail($order),
            // A customer who cancelled their own order must never be told
            // they failed to collect it, so only an admin no-show
            // cancellation has an email to repeat.
            'cancelled' => $order->cancellation_reason === 'customer_no_show'
                ? new OrderCancelledNoShowMail($order)
                : null,
            // Nothing is emailed at checkout, so a pending order has no
            // status email yet - the processing one is its first.
            default => null,
        };
    }

    /**
     * Send the current status email again, for when the first attempt failed
     * or the customer says it never arrived.
     *
     * The panel has the same escape hatch for an admin invitation that could
     * not be emailed (UserResource::sendInvitation), and the warning shown by
     * notifyCustomerByMail() points here by name. Nothing about the order
     * changes, so this is authorized as an edit but writes no history row.
     */
    public static function resendStatusEmailAction(): Action
    {
        return Action::make('resend_status_email')
            ->label('Resend email')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->authorize(fn (Order $record): bool => self::canEdit($record))
            ->visible(fn (Order $record): bool => self::statusMailableFor($record) instanceof Mailable)
            ->requiresConfirmation()
            ->modalHeading('Resend the order email')
            ->modalDescription(fn (Order $record): string => 'Email '.($record->customer?->email ?? 'the customer')
                .' another copy of the '.Str::lower(Str::headline($record->status)).' message for '
                .$record->order_number.'? Nothing about the order changes.')
            ->modalSubmitActionLabel('Resend email')
            ->action(function (Order $record): void {
                // Re-read first: the status may have moved on in another tab,
                // and resending the email for a status the order has left
                // would tell the customer something untrue.
                $record->refresh();

                $mailable = self::statusMailableFor($record);

                if (! $mailable instanceof Mailable) {
                    Notification::make()
                        ->title('Nothing to resend')
                        ->body('This order has no email for its current status.')
                        ->warning()
                        ->send();

                    return;
                }

                self::notifyCustomerByMail($record, $mailable, 'Order email resent');
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
