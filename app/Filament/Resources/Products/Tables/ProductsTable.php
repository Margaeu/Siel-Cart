<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Deleted products are listed too, under the Trash tab; the tabs
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
                    // Avoid a blocking R2 existence request for every row.
                    ->checkFileExistence(false)
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                // A variant product has no SKU of its own -- each variant
                // carries one -- so a blank cell read as missing data. Say
                // why it is empty instead, using the same "With variants"
                // wording as the view page's Product type badge. Placeholder
                // text renders greyed out and isn't copied by copyable().
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->placeholder(fn (Product $record): string => $record->has_variants ? 'With variants' : '—'),
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
                // No is_active or Trashed filter: the All/Active/Inactive/Trash
                // tabs on ListProducts cover both, and a TrashedFilter's default
                // "without deleted" would leave the Trash tab empty.
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Same wording as the edit page: see EditProduct.
                    DeleteBulkAction::make()
                        ->label('Move to trash')
                        ->modalHeading('Move selected products to trash')
                        ->modalDescription('The products are hidden from the storefront and moved to the Trash tab. You can restore them later.')
                        ->modalSubmitActionLabel('Move to trash')
                        ->successNotificationTitle('Moved to trash'),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make()
                        ->modalDescription('The products will be restored as inactive. Activate each one when it is ready to go back on the storefront.'),
                ]),
            ]);
    }
}
