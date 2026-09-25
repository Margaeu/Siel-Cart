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
use Illuminate\Support\Facades\Mail;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            // The form shows the pickup schedule, so it is refilled after a
            // reschedule or it would save the old dates back on the next submit.
            OrderResource::reschedulePickupAction()
                ->after(fn () => $this->fillForm()),
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

                    Mail::to($order->customer->email)->send(new OrderCancelledNoShowMail($order));

                    $this->record = $order;
                    $this->fillForm();

                    Notification::make()
                        ->title('Order cancelled')
                        ->body('The items were released and the customer was notified by email.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
