<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\Actions\DeleteCustomerAccountAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Read-only account summary plus the customer's retained history (orders,
 * reviews, reports filed and received) as relation manager tabs. This is the
 * only page a deleted customer opens on: CustomerResource::canEdit() refuses
 * trashed records.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction authorizes through the policy, not canEdit(), so the
            // deleted-record rule has to be repeated to hide the button.
            EditAction::make()
                ->hidden(fn (Customer $record): bool => $record->trashed()),
            DeleteCustomerAccountAction::make(),
        ];
    }
}
