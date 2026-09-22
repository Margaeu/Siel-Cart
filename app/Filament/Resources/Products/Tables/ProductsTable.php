<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Deleted products are listed too, under the Deleted tab; the tabs
            // on ListProducts apply the deleted_at condition themselves.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with(['variants', 'cardImage']))
            ->columns([
                // cardImage, not primaryImage: a variant product often has no
                // shared primary photo, only per-variant ones, and primaryImage
                // left its thumbnail blank. cardImage falls back to the first
                // active variant's image, the same one the storefront card shows.
                ImageColumn::make('cardImage.image_path')
                    ->label('')
                    ->disk('r2')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->sortable(),
                // A variant product's own price column is not what it sells
                // for, so show and sort on the same figure the storefront
                // does: its cheapest active variant.
                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn (Product $record) => $record->display_price_label)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByDisplayPrice($direction)),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->state(fn (Product $record) => $record->has_variants
                        ? $record->variants->where('is_active', true)->sum('stock_quantity')
                        : $record->stock_quantity)
                    ->numeric(),
                TextColumn::make('stock_status')
                    ->label('Availability')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'in_stock' ? 'In Stock' : 'Out of Stock')
                    ->color(fn (string $state) => $state === 'in_stock' ? 'success' : 'danger'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'danger'),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                // No is_active or Trashed filter: the All/Active/Inactive/Deleted
                // tabs on ListProducts cover both, and a TrashedFilter's default
                // "without deleted" would leave the Deleted tab empty.
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
