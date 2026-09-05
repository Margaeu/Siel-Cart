<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Banner Image')
                    ->description('This image is displayed full-width in the homepage carousel.')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Banner Image')
                            ->disk('r2')
                            ->visibility('public')
                            ->directory('banners')
                            ->image()
                            ->required()
                            ->maxSize(2048)
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9', '21:9', null])
                            ->orientImagesFromExif(false)
                            ->imagePreviewHeight('250')
                            ->helperText('Recommended: wide landscape image (e.g. 1920×720). Max 2MB.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->default(null),
                        TextInput::make('subtitle')
                            ->default(null),
                    ])->columns(2),
                Section::make('Display')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->helperText('Lower numbers appear first in the carousel.')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }
}
