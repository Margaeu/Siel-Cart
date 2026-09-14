<?php

namespace App\Filament\Resources\ReturnRefunds\Pages;

use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReturnRefunds extends ListRecords
{
    protected static string $resource = ReturnRefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record refund or exchange'),
        ];
    }
}
