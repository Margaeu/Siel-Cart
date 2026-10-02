<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category information')
                ->description('Name and slug of this storefront collection.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ])
                ->schema([
                    TextEntry::make('name')
                        ->label('Name')
                        ->weight(FontWeight::Bold),

                    TextEntry::make('slug')
                        ->label('Slug')
                        ->copyable(),
                ]),

            Section::make('Category image')
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('image')
                        ->hiddenLabel()
                        ->disk('r2')
                        ->imageHeight(220)
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
                        ->label('Order')
                        ->numeric(),

                    TextEntry::make('products_count')
                        ->label('Products')
                        // Same format as the table's Products column: trashed
                        // products still block deletion, so they are shown.
                        ->state(function (Category $record): string {
                            $live = $record->products()->count();
                            $trashed = $record->products()->onlyTrashed()->count();

                            return $trashed > 0 ? "{$live} (+{$trashed} in trash)" : (string) $live;
                        }),

                    TextEntry::make('created_at')
                        ->label('Created at')
                        ->dateTime('M d, Y - h:i A'),

                    TextEntry::make('updated_at')
                        ->label('Last updated')
                        ->dateTime('M d, Y - h:i A'),
                ]),

            Section::make('Products in this category')
                ->columnSpanFull()
                ->schema([
                    // Variants and the stock aggregate are loaded up front so the
                    // price label and stock status don't query once per row.
                    RepeatableEntry::make('products')
                        ->hiddenLabel()
                        ->state(fn (Category $record) => $record->products()
                            ->with('variants')
                            ->withStockAggregates()
                            ->orderBy('name')
                            ->get())
                        ->table([
                            TableColumn::make('Product'),
                            TableColumn::make('SKU'),
                            TableColumn::make('Price'),
                            TableColumn::make('Stock'),
                            TableColumn::make('Status'),
                        ])
                        ->schema([
                            TextEntry::make('name')
                                ->url(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
                                ->color('primary'),
                            // A variable product keeps its SKUs on the variants, so its
                            // own sku column is empty; say so instead of showing a dash.
                            TextEntry::make('sku')
                                ->state(fn (Product $record): string => $record->has_variants
                                    ? 'With variants'
                                    : ($record->sku ?: '—')),
                            TextEntry::make('display_price_label'),
                            TextEntry::make('stock_status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => $state === 'in_stock' ? 'In stock' : 'Out of stock')
                                ->color(fn (string $state): string => $state === 'in_stock' ? 'success' : 'danger'),
                            TextEntry::make('is_active')
                                ->badge()
                                ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                                ->color(fn (mixed $state): string => $state ? 'success' : 'gray'),
                        ])
                        ->placeholder('No products in this category yet'),
                ]),
        ]);
    }
}
