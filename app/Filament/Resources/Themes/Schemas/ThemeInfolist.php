<?php

namespace App\Filament\Resources\Themes\Schemas;

use App\Models\Theme;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class ThemeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Theme')
                ->description('Name of this storefront style and whether it is currently in use.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ])
                ->schema([
                    TextEntry::make('name')
                        ->label('Theme name')
                        ->weight(FontWeight::Bold),

                    // Only one theme is active at a time (Theme::booted()), so an
                    // inactive theme here is simply not what the storefront shows.
                    TextEntry::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state ? 'Active on storefront' : 'Inactive')
                        ->color(fn (mixed $state): string => $state ? 'success' : 'danger'),
                ]),

            Section::make('Colors')
                ->description('Institutional and supporting accent colors used across the storefront.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ])
                ->schema([
                    ColorEntry::make('primary_color')
                        ->label('Primary color')
                        ->copyable()
                        ->copyMessage('Hex code copied'),

                    ColorEntry::make('secondary_color')
                        ->label('Secondary color')
                        ->copyable()
                        ->copyMessage('Hex code copied'),

                    // The swatches alone don't show the value, so the hex codes are
                    // spelled out for admins matching them against brand guides.
                    TextEntry::make('primary_color_hex')
                        ->label('Primary hex')
                        ->state(fn (Theme $record): ?string => $record->primary_color)
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),

                    TextEntry::make('secondary_color_hex')
                        ->label('Secondary hex')
                        ->state(fn (Theme $record): ?string => $record->secondary_color)
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),
                ]),

            Section::make('History')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ])
                ->schema([
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
