<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use App\Models\OrderItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The ordered products table summarises each line's resolutions, so they
     * are loaded once for the page rather than once per line.
     */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load('items.resolutions');
    }

    /**
     * Refunds and exchanges are recorded and read in full under Returns &
     * Refunds. The order page only links there.
     */
    protected function getHeaderActions(): array
    {
        $order = $this->getRecord();

        return [
            Action::make('record_resolution')
                ->label('Record refund or exchange')
                ->icon('heroicon-o-receipt-refund')
                ->color('secondary')
                ->url(fn (): string => ReturnRefundResource::getUrl('create', ['order' => $order->getKey()]))
                ->visible(fn (): bool => $order->canRecordItemResolutions() && ReturnRefundResource::canCreate()),

            Action::make('view_resolutions')
                ->label('Returns & refunds')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->badge(fn (): int => $order->items->sum(fn (OrderItem $item): int => $item->resolutions->count()))
                ->url(fn (): string => ReturnRefundResource::getUrl('index', ['search' => $order->order_number]))
                ->visible(fn (): bool => ReturnRefundResource::canViewAny()
                    && $order->items->contains(fn (OrderItem $item): bool => $item->resolutions->isNotEmpty())),

            EditAction::make(),
        ];
    }
}
