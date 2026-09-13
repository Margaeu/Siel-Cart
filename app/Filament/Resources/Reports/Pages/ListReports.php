<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    /**
     * Reports are only ever filed by customers from the storefront, so there
     * is no admin create action here.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}