<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderCancelledNoShowMail;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            OrderResource::resendStatusEmailAction(),
            Action::make('cancel_no_show')
                ->label('Cancel No-show Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel order for missed pickup?')
                ->modalDescription('This marks the payment as failed, releases the reserved items, and emails the customer. This action is only for an order not collected by the end of its scheduled pickup period.')
                ->modalSubmitActionLabel('Cancel order')
                ->visible(fn (): bool => $this->record->canBeCancelledForNoShow())
                ->action(function (): void {
                    $order = DB::transaction(function (): ?Order {
                        $order = Order::query()
                            ->whereKey($this->record->getKey())
                            ->lockForUpdate()
                            ->first();

                        if (! $order?->canBeCancelledForNoShow()) {
                            return null;
                        }

                        $order->updateStatus(
                            'cancelled',
                            'Order cancelled by admin because the customer did not collect it during the scheduled pickup period.',
                            auth()->id(),
                            [
                                'payment_status' => 'failed',
                                'cancellation_reason' => 'customer_no_show',
                                'cancelled_at' => now(),
                            ],
                        );

                        return $order->fresh(['customer', 'items']);
                    });

                    if (! $order) {
                        Notification::make()
                            ->title('Order cannot be cancelled')
                            ->body('Only a ready-for-pickup order whose scheduled pickup period has ended can be cancelled as a no-show.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record = $order;
                    $this->fillForm();

                    OrderResource::notifyCustomerByMail(
                        $order,
                        new OrderCancelledNoShowMail($order),
                        'Order cancelled',
                        'The reserved items were released.',
                    );
                }),
            // Deleting is not cancelling. Neither action returns stock -- only
            // the move to `cancelled` does (Order::RESTOCKING_STATUSES) -- and
            // neither emails the customer, so both modals say so: an admin
            // "removing" a pending order would otherwise strand its reserved
            // units for good.
            DeleteAction::make()
                ->modalHeading(fn (Order $record): string => "Delete order {$record->order_number}?")
                ->modalDescription('The order is hidden from the order lists and from the customer\'s order history, and can be restored later (filter the order list by "Deleted records"). Deleting does not cancel it: reserved items are not returned to stock and the customer is not emailed. To release the items, cancel the order instead.'),
            ForceDeleteAction::make()
                ->modalHeading(fn (Order $record): string => "Permanently delete order {$record->order_number}?")
                ->modalDescription('The order, its items and its status history are erased for good and drop out of sales figures. This cannot be undone and is not the same as cancelling. Restore and cancel the order instead if it only needs to be voided.'),
        ];
    }
}
