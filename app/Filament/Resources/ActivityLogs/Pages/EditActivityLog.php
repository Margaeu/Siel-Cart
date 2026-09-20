<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Unreachable scaffold: ActivityLogResource registers no "edit" route and
 * refuses canEdit() for everyone. Kept only until it is removed deliberately;
 * it carries no actions so it cannot become a way to alter the audit trail.
 */
class EditActivityLog extends EditRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
