<?php

namespace App\Filament\Resources\Banners\Tables;

use App\Models\Banner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('r2')
                    ->checkFileExistence(false)
                    ->square()
                    // A clip's URL is a real file but not one an <img> can draw, so
                    // returning null here hands the column over to defaultImageUrl
                    // instead of rendering a broken-image icon in every video row.
                    ->getStateUsing(fn (Banner $record): ?string => $record->is_video ? null : $record->image_path)
                    // Inline so the placeholder cannot break with a missing asset,
                    // and so it inherits the row's text colour like Filament's own icons.
                    ->defaultImageUrl('data:image/svg+xml;utf8,'.rawurlencode(
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.5">'
                        .'<rect x="2.5" y="5.5" width="19" height="13" rx="2"/>'
                        .'<path d="M10 9.5l5 2.5-5 2.5z" fill="#6b7280" stroke="none"/></svg>'
                    )),
                TextColumn::make('media_type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (Banner $record): string => $record->is_video ? 'Clip' : 'Image')
                    ->color(fn (string $state): string => $state === 'Clip' ? 'info' : 'gray'),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
