<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.users.profile')
                ->viewData(fn (User $record): array => ['user' => $record])
                ->columnSpanFull(),
        ]);
    }
}
