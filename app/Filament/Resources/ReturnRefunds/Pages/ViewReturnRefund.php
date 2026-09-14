<?php

namespace App\Filament\Resources\ReturnRefunds\Pages;

use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only: a recorded refund or exchange is history, so there is no edit or
 * delete action to offer.
 */
class ViewReturnRefund extends ViewRecord
{
    protected static string $resource = ReturnRefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_order')
                ->label('Open order')
                ->icon('heroicon-o-shopping-cart')
                ->color('gray')
                ->url(fn (): ?string => ReturnRefundResource::getOrderUrl($this->getRecord()))
                ->visible(fn (): bool => filled(ReturnRefundResource::getOrderUrl($this->getRecord()))),
        ];
    }
}
