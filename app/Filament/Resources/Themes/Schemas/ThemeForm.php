<?php

namespace App\Filament\Resources\Themes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Theme')
                    ->description('Name this storefront style and choose whether it is currently in use.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Theme name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('e.g. "Default Green", "Christmas Promo".'),

                        Toggle::make('is_active')
                            ->label('Make this the active theme')
                            ->helperText('Only one theme can be active. Turning this on switches every other theme off.')
                            ->default(false),
                    ])->columns(2),

                Section::make('Colors')
                    ->description('Choose the main institutional and supporting accent colors used across the storefront.')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primary color')
                            ->required()
                            ->default('#1E6031'),

                        ColorPicker::make('secondary_color')
                            ->label('Secondary color')
                            ->required()
                            ->default('#E0A70D'),
                    ])->columns(2),
            ]);
    }
}
