<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    /**
     * Reviews are only ever created by customers who have a completed order,
     * so there is no admin create action here.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
