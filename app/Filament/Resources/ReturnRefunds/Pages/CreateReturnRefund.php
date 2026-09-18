<?php

namespace App\Filament\Resources\ReturnRefunds\Pages;

use App\Enums\OrderItemResolutionType;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ReturnRefundResolutionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateReturnRefund extends CreateRecord
{
    protected static string $resource = ReturnRefundResource::class;

    protected static ?string $title = 'Record a refund or exchange';

    protected static bool $canCreateAnother = false;

    /**
     * The order page links here with ?order=, so the admin starts on that
     * order. Anything that cannot take a refund or exchange is ignored.
     */
    protected function afterFill(): void
    {
        $order = Order::query()->find(request()->integer('order'));

        if ($order?->canRecordItemResolutions()) {
            $this->data['order_id'] = $order->getKey();
        }
    }

    /**
     * Never a plain insert. The service checks the record holds up, keeps stock
     * right for an exchange, and writes it. Anything it refuses is shown against
     * the field responsible, and the form stays as the admin filled it in.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $type = $data['type'] instanceof OrderItemResolutionType
            ? $data['type']
            : OrderItemResolutionType::from($data['type']);

        try {
            // Looked up within the chosen order, so a line from another order
            // cannot be named.
            $item = OrderItem::query()
                ->where('order_id', (int) ($data['order_id'] ?? 0))
                ->find((int) ($data['order_item_id'] ?? 0));

            if (! $item) {
                throw ValidationException::withMessages(['order_item_id' => 'Choose a line from this order.']);
            }

            $service = app(ReturnRefundResolutionService::class);

            return $type === OrderItemResolutionType::Refund
                ? $service->recordRefund($item, auth()->user(), $data)
                : $service->recordExchange($item, auth()->user(), $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title("{$type->getLabel()} not recorded")
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ["data.{$field}" => $messages])
                ->all());
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "{$this->getRecord()->type->getLabel()} recorded";
    }
}
