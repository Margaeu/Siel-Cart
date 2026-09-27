<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Models\Banner;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Banner media')
                ->description('Displayed full-width in the homepage carousel.')
                ->columnSpanFull()
                ->schema([
                    // A banner is either a still or a clip, so exactly one of these
                    // two entries renders. ImageEntry has no video branch -- an MP4's
                    // URL is a real file but not one an <img> can draw -- so clips go
                    // to the Blade view, which gives the admin playback controls the
                    // storefront slide deliberately omits.
                    ImageEntry::make('image_path')
                        ->hiddenLabel()
                        ->disk('r2')
                        ->imageHeight(280)
                        ->imageWidth('100%')
                        ->extraImgAttributes(['class' => 'object-cover rounded-lg'])
                        ->placeholder('No image uploaded')
                        ->hidden(fn (Banner $record): bool => $record->is_video),

                    ViewEntry::make('image_path')
                        ->hiddenLabel()
                        ->view('filament.banners.media-preview')
                        ->visible(fn (Banner $record): bool => $record->is_video),
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
