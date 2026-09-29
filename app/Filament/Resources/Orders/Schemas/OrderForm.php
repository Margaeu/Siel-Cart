<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Internal notes')
                    ->description('Add context for administrators. Customers do not see these notes.')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(4)
                            ->default(null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
