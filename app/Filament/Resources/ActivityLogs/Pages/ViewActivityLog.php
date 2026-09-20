<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Support\ActivityLogPresenter;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    // No header actions: an audit record is never edited or deleted.
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return ActivityLogPresenter::for($this->getRecord())->eventLabel().' activity';
    }
}
