<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Resources\Banners\BannerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Banners are hard-deleted (no SoftDeletes), so the modal says it is
            // final and points at the reversible alternative.
            DeleteAction::make()
                ->modalHeading('Delete this banner?')
                ->modalDescription('It is removed from the homepage carousel immediately and cannot be restored. To take it down for now and bring it back later, turn off "Active" instead.'),
        ];
    }
}
