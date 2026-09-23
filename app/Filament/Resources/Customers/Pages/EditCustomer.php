<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    // No delete, force-delete or restore action of any kind: only the
    // customer can delete their own account (Customer::deleteAccount()), and a
    // deleted account is never restored. See CustomerResource::canDelete().
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
