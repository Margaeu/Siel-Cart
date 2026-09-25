<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    // No force-delete or restore action of any kind: a deleted account is
    // never restored, and force delete would cascade through orders, reviews
    // and reports. Delete goes through deleteAccountAction(), not Filament's
    // generic DeleteAction — see CustomerResource::canDelete() for who it's
    // shown to.
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            CustomerResource::deleteAccountAction(CustomerResource::getUrl('index')),
        ];
    }
}
