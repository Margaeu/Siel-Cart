<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    // No CreateAction: customers register themselves on the storefront and are
    // never created from the panel. See CustomerResource::canCreate().
    protected function getHeaderActions(): array
    {
        return [];
    }
}
