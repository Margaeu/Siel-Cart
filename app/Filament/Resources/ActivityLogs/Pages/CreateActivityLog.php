<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Unreachable scaffold: ActivityLogResource registers no "create" route and
 * refuses canCreate() for everyone. Activity is only written by the app.
 */
class CreateActivityLog extends CreateRecord
{
    protected static string $resource = ActivityLogResource::class;
}
