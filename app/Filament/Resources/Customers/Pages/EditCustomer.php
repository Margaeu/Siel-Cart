<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\Actions\DeleteCustomerAccountAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    // No DeleteAction / ForceDeleteAction / RestoreAction: see
    // CustomerResource::canDelete() and DeleteCustomerAccountAction.
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteCustomerAccountAction::make(),
        ];
    }
}
