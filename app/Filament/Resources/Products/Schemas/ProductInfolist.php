<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product information')
                ->description('Name, category and the copy customers see on the product page.')
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

                    TextEntry::make('category.name')
                        ->label('Category')
                        ->badge()
                        ->url(fn (Product $record): ?string => $record->category
                            ? CategoryResource::getUrl('view', ['record' => $record->category])
                            : null)
                        ->placeholder('Uncategorized'),

                    TextEntry::make('has_variants')
                        ->label('Product type')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state ? 'With variants' : 'Simple product')
                        ->color(fn (mixed $state): string => $state ? 'info' : 'gray'),

                    TextEntry::make('short_description')
                        ->label('Short description')
                        ->placeholder('No short description')
                        ->columnSpanFull(),

                    // Written with the RichEditor, so render the stored HTML.
                    TextEntry::make('description')
                        ->label('Description')
                        ->html()
                        ->placeholder('No description')
                        ->columnSpanFull(),
                ]),

            // A variant product often has no shared photos, only per-variant
            // ones. Rather than report "No images uploaded" for a product the
            // storefront does show a picture for, fall back to cardImage — the
            // first active variant's image, the same one the product card and
            // the admin list thumbnail use — and say where it came from.
            Section::make('Product images')
                ->description(fn (Product $record): string => $record->generalImages->isEmpty() && $record->cardImage
                    ? 'No shared images uploaded. Showing the first variant image, which the storefront uses as this product\'s picture.'
                    : 'Shared images shown for every variant. The first image is the primary image.')
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('generalImages.image_path')
                        ->hiddenLabel()
                        ->state(fn (Product $record): array => $record->generalImages->isNotEmpty()
                            ? $record->generalImages->pluck('image_path')->all()
                            : array_filter([$record->cardImage?->image_path]))
                        ->disk('r2')
                        ->imageHeight(180)
                        ->placeholder('No images uploaded'),
                ]),

            // Simple products carry their own price and stock. A variant
            // product's columns here are not what it sells, so this section
            // gives way to the variants table below.
            Section::make('Pricing & inventory')
                ->columnSpanFull()
                ->visible(fn (Product $record): bool => ! $record->has_variants)
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->schema([
                    TextEntry::make('sku')
                        ->label('SKU')
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('price')
                        ->label('Price')
                        ->money('PHP'),

                    TextEntry::make('stock_quantity')
                        ->label('Stock quantity')
                        ->numeric(),

                    TextEntry::make('low_stock_threshold')
                        ->label('Low stock alert')
                        ->formatStateUsing(fn (mixed $state): string => (int) $state > 0 ? "At or below {$state}" : 'Disabled'),

                    TextEntry::make('stock_level')
                        ->label('Availability')
                        ->badge()
                        ->state(fn (Product $record): string => self::stockLevel($record->stock_quantity, $record->low_stock_threshold))
                        ->formatStateUsing(fn (string $state): string => self::stockLevelLabel($state))
                        ->color(fn (string $state): string => self::stockLevelColor($state)),
                ]),

            Section::make('Product variants')
                ->description('Each variant is priced and stocked on its own. Only active variants are sold.')
                ->columnSpanFull()
                ->visible(fn (Product $record): bool => $record->has_variants)
                ->schema([
                    TextEntry::make('display_price_label')
                        ->label('Storefront price'),

                    // Images are loaded up front so the thumbnails don't query
                    // once per row.
                    RepeatableEntry::make('variants')
                        ->hiddenLabel()
                        ->state(fn (Product $record) => $record->variants()->with('images')->get())
                        ->table([
                            TableColumn::make('Image'),
                            TableColumn::make('Variant'),
                            TableColumn::make('SKU'),
                            TableColumn::make('Price'),
                            TableColumn::make('Stock'),
                            TableColumn::make('Availability'),
                            TableColumn::make('Status'),
                        ])
                        ->schema([
                            ImageEntry::make('images.image_path')
                                ->disk('r2')
                                ->imageHeight(40)
                                ->square()
                                ->stacked()
                                ->limit(3)
                                ->placeholder('—'),
                            TextEntry::make('name')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('sku')
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('price')
                                ->money('PHP'),
                            TextEntry::make('stock_quantity')
                                ->numeric(),
                            TextEntry::make('stock_level')
                                ->badge()
                                ->state(fn ($record): string => self::stockLevel($record->stock_quantity, $record->low_stock_threshold))
                                ->formatStateUsing(fn (string $state): string => self::stockLevelLabel($state))
                                ->color(fn (string $state): string => self::stockLevelColor($state)),
                            TextEntry::make('is_active')
                                ->badge()
                                ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                                ->color(fn (mixed $state): string => $state ? 'success' : 'gray'),
                        ])
                        ->placeholder('No variants added yet'),
                ]),

            Section::make('Status & activity')
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

                    IconEntry::make('is_featured')
                        ->label('Featured')
                        ->boolean(),

                    TextEntry::make('stock_status')
                        ->label('Storefront availability')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => $state === 'in_stock' ? 'In stock' : 'Out of stock')
                        ->color(fn (string $state): string => $state === 'in_stock' ? 'success' : 'danger'),

                    TextEntry::make('views_count')
                        ->label('Views')
                        ->numeric(),

                    TextEntry::make('average_rating')
                        ->label('Average rating')
                        ->formatStateUsing(fn (Product $record): string => $record->reviews_count > 0
                            ? number_format($record->average_rating, 1) . ' / 5 (' . $record->reviews_count . ' ' . str('review')->plural($record->reviews_count) . ')'
                            : 'No approved reviews'),

                    TextEntry::make('created_at')
                        ->label('Created at')
                        ->dateTime('M d, Y - h:i A'),

                    TextEntry::make('updated_at')
                        ->label('Last updated')
                        ->dateTime('M d, Y - h:i A'),

                    TextEntry::make('deleted_at')
                        ->label('Moved to trash')
                        ->dateTime('M d, Y - h:i A')
                        ->color('danger')
                        ->visible(fn (Product $record): bool => $record->trashed()),
                ]),
        ]);
    }

    /**
     * A threshold of 0 disables the low stock alert, matching the form's help text.
     */
    private static function stockLevel(?int $quantity, ?int $threshold): string
    {
        $quantity ??= 0;
        $threshold ??= 0;

        return match (true) {
            $quantity <= 0 => 'out_of_stock',
            $threshold > 0 && $quantity <= $threshold => 'low_stock',
            default => 'in_stock',
        };
    }

    private static function stockLevelLabel(string $state): string
    {
        return match ($state) {
            'out_of_stock' => 'Out of stock',
            'low_stock' => 'Low stock',
            default => 'In stock',
        };
    }

    private static function stockLevelColor(string $state): string
    {
        return match ($state) {
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            default => 'success',
        };
    }
}
