<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Banner image')
                ->description('Displayed full-width in the homepage carousel.')
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('image_path')
                        ->hiddenLabel()
                        ->disk('r2')
                        ->imageHeight(280)
                        ->imageWidth('100%')
                        ->extraImgAttributes(['class' => 'object-cover rounded-lg'])
                        ->placeholder('No image uploaded'),
                ]),

            Section::make('Display settings')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->schema([
                    TextEntry::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                        ->color(fn (mixed $state): string => $state ? 'success' : 'danger'),

                    TextEntry::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->helperText('Lower numbers appear first.'),

                    TextEntry::make('created_at')
                        ->label('Created at')
                        ->dateTime('M d, Y - h:i A'),

                    TextEntry::make('updated_at')
                        ->label('Last updated')
                        ->dateTime('M d, Y - h:i A'),
                ]),
        ]);
    }
}
