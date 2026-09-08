<?php

namespace App\Filament\Resources\Themes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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

                Section::make('Typography')
                    ->description('Pick a pre-approved web font, or upload your own font file below. An uploaded font always takes priority over the preset.')
                    ->schema([
                        Select::make('font_family')
                            ->label('Preset font')
                            ->options([
                                'Inter' => 'Inter',
                                'Poppins' => 'Poppins',
                                'Roboto' => 'Roboto',
                                'Nunito' => 'Nunito',
                                'Merriweather' => 'Merriweather',
                                'Playfair Display' => 'Playfair Display',
                            ])
                            ->native(false)
                            ->default('Inter'),

                        TextInput::make('custom_font_name')
                            ->label('Custom font name')
                            ->helperText('The name this font will be registered under in CSS, e.g. "CLSU Sans".')
                            ->maxLength(100),

                        FileUpload::make('custom_font_path')
                            ->label('Custom font file')
                            ->disk('r2')
                            ->visibility('public')
                            ->directory('fonts')
                            ->acceptedFileTypes([
                                'font/woff2',
                                'font/woff',
                                'font/ttf',
                                'application/font-woff',
                                'application/x-font-ttf',
                            ])
                            ->maxSize(2048)
                            ->helperText('.woff2 recommended. Max 2MB. Leave empty to use the preset font above instead.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
