<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product details')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Basic Information')
                            ->icon(Heroicon::InformationCircle)
                            ->schema([
                                Section::make('Product Details')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required(),
                                        TextInput::make('slug')
                                            ->unique(ignoreRecord: true)
                                            ->visible(fn(string $operation) => $operation === 'edit')
                                            ->required(),
                                        Select::make('category_id')
                                            ->relationship('category', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->required(),
                                                TextInput::make('slug')
                                                    ->unique(ignoreRecord: true)
                                                    ->readOnly()
                                                    ->visibleOn('edit'),
                                            ]),
                                    ])->columns(2),
                                Section::make('Product Description')
                                    ->schema([
                                        Textarea::make('short_description')
                                            ->default(null)
                                            ->columnSpanFull(),
                                        RichEditor::make('description')
                                            ->default(null)
                                            ->columnSpanFull(),
                                    ])
                            ]),
                        Tab::make('Pricing & Inventory')
                            ->schema([
                                Section::make('Pricing')
                                    ->schema([
                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Stock keeping Unit - unique identifier')
                                            ->required(),
                                        TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->helperText('Selling Price')
                                            ->prefix('₱'),
                                    ])->columns(2),
                                Section::make('Inventory')
                                    ->schema([
                                        Toggle::make('manage_stock')
                                            ->default(true)
                                            ->helperText('Enable stock management for this product')
                                            ->live(),
                                        TextInput::make('stock_quantity')
                                            ->label('Stock Quantity')
                                            ->required(fn(callable $get) => $get('manage_stock'))
                                            ->disabled(fn(callable $get) => !$get('manage_stock'))
                                            ->numeric()
                                            ->default(0),
                                        TextInput::make('low_stock_threshold')
                                            ->label('Low stock Alert Threshold')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Get notified when stock falls below this number'),
                                        Select::make('stock_status')
                                            ->options([
                                                'in_stock' => 'In Stock',
                                                'out_of_stock' => 'Out of Stock',
                                            ])
                                            ->native(false)
                                            ->default('in_stock')
                                            ->required(),
                                    ])
                                    ->columns(2)
                            ]),
                        Tab::make('Images')
                            ->icon(Heroicon::Photo)
                            ->schema([
                                Section::make('Product Images')
                                    ->description('Shared images shown for every variant — size charts, packaging, model displays. The first image will be the primary image. Per-variant photos are uploaded under the Product Variants tab.')
                                    ->schema([
                                        FileUpload::make('generalImages')
                                            ->disk('r2')
                                            ->visibility('public')
                                            ->label('Product Images')
                                            ->multiple()
                                            ->image()
                                            ->directory('products')
                                            ->maxSize(2048)
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->orientImagesFromExif(false)
                                            ->imagePreviewHeight('250')
                                            ->fetchFileInformation(false)
                                            ->extraAttributes([
                                                'data-filepond-type' => 'image',
                                            ])
                                            ->extraInputAttributes([
                                                'data-filepond-item-property-size' => 'false',
                                            ])
                                            ->helperText('You can drag and drop to reorder images')
                                            ->afterStateHydrated(function (FileUpload $component, ?Product $record): void {
                                                // `generalImages` is a relationship, not a column, so Filament's
                                                // `attributesToArray()` fill leaves this field empty.
                                                //
                                                // This deliberately replaces BaseFileUpload::hydrateFiles(),
                                                // whose `$disk->exists()` check drops paths whose R2 object is
                                                // missing and treats an unreachable R2 as "missing". An empty
                                                // state would then read as "cleared the gallery" on save.
                                                //
                                                // Only shared images load here; variant images belong to the
                                                // variant's own uploader and must not be listed (or deleted) by
                                                // this field.
                                                $component->state(
                                                    $record?->generalImages
                                                        ->pluck('image_path')
                                                        ->filter()
                                                        ->values()
                                                        ->all() ?? [],
                                                );
                                            })
                                            ->saveRelationshipsUsing(function ($state, ?Product $record): void {
                                                if (! $record) {
                                                    return;
                                                }

                                                // Uploads are already persisted and cast to path strings by
                                                // `saveUploadedFiles()`, which runs before this callback.
                                                $paths = collect(Arr::wrap($state))
                                                    ->filter(fn ($path): bool => is_string($path) && filled($path))
                                                    ->values()
                                                    ->all();

                                                // Remove only what left the field, so images that survived the
                                                // edit keep their existing R2 object and row. Scoped to
                                                // `generalImages` so variant photos are never touched here.
                                                foreach ($record->generalImages()->get() as $existingImage) {
                                                    if (in_array($existingImage->image_path, $paths, true)) {
                                                        continue;
                                                    }

                                                    Storage::disk('r2')->delete($existingImage->image_path);
                                                    $existingImage->delete();
                                                }

                                                foreach ($paths as $index => $imagePath) {
                                                    $record->generalImages()->updateOrCreate(
                                                        ['image_path' => $imagePath],
                                                        [
                                                            'is_primary' => $index === 0,
                                                            'sort_order' => $index,
                                                        ],
                                                    );
                                                }

                                                $record->unsetRelation('images')
                                                    ->unsetRelation('generalImages')
                                                    ->unsetRelation('primaryImage');
                                            })
                                            ->dehydrated(false)
                                    ])
                            ]),
                        Tab::make('Product Variants')
                            ->icon(Heroicon::Squares2x2)
                            ->schema([
                                Toggle::make('has_variants')
                                    ->live()
                                    ->required(),
                                Section::make('Product Variants')
                                    ->description('Add variants like different sizes or colors')
                                    ->schema([
                                        Repeater::make('variants')
                                            ->relationship('variants')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label('Variant Name')
                                                    ->placeholder('e.g., Red - Large'),
                                                TextInput::make('sku')
                                                    ->label('SKU')
                                                    ->unique(ignoreRecord: true)
                                                    ->helperText('Stock keeping Unit - unique identifier')
                                                    ->required()
                                                    ->columnSpan(2),
                                                TextInput::make('price')
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('₱')
                                                    ->minValue(0)
                                                    ->step(0.01),
                                                TextInput::make('stock_quantity')
                                                    ->label('Stock')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->required(),
                                                Select::make('stock_status')
                                                    ->options([
                                                        'in_stock' => 'In Stock',
                                                        'out_of_stock' => 'Out of Stock',
                                                        'on_backorder' => 'On Backorder',
                                                    ])
                                                    ->default('in_stock')
                                                    ->required()
                                                    ->native(false),
                                                Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true),
                                                FileUpload::make('images')
                                                    ->disk('r2')
                                                    ->visibility('public')
                                                    ->label('Variant Images')
                                                    ->helperText('Shown when a customer selects this variant. Leave empty to keep showing the shared product images.')
                                                    ->multiple()
                                                    ->image()
                                                    ->directory('products/variants')
                                                    ->maxSize(2048)
                                                    ->reorderable()
                                                    ->columnSpanFull()
                                                    ->orientImagesFromExif(false)
                                                    ->imagePreviewHeight('150')
                                                    ->fetchFileInformation(false)
                                                    ->extraAttributes([
                                                        'data-filepond-type' => 'image',
                                                    ])
                                                    ->extraInputAttributes([
                                                        'data-filepond-item-property-size' => 'false',
                                                    ])
                                                    // Same reasoning as the shared gallery above: `images` is a
                                                    // relationship, and BaseFileUpload's `$disk->exists()` check
                                                    // would silently drop paths when R2 is unreachable.
                                                    ->afterStateHydrated(function (FileUpload $component, ?ProductVariant $record): void {
                                                        $component->state(
                                                            $record?->images
                                                                ->pluck('image_path')
                                                                ->filter()
                                                                ->values()
                                                                ->all() ?? [],
                                                        );
                                                    })
                                                    ->saveRelationshipsUsing(function ($state, ?ProductVariant $record): void {
                                                        if (! $record) {
                                                            return;
                                                        }

                                                        $paths = collect(Arr::wrap($state))
                                                            ->filter(fn ($path): bool => is_string($path) && filled($path))
                                                            ->values()
                                                            ->all();

                                                        foreach ($record->images()->get() as $existingImage) {
                                                            if (in_array($existingImage->image_path, $paths, true)) {
                                                                continue;
                                                            }

                                                            Storage::disk('r2')->delete($existingImage->image_path);
                                                            $existingImage->delete();
                                                        }

                                                        foreach ($paths as $index => $imagePath) {
                                                            $record->images()->updateOrCreate(
                                                                ['image_path' => $imagePath],
                                                                [
                                                                    'product_id' => $record->product_id,
                                                                    // `is_primary` stays false: it marks the
                                                                    // product's card thumbnail, which is always
                                                                    // a shared image.
                                                                    'is_primary' => false,
                                                                    'sort_order' => $index,
                                                                ],
                                                            );
                                                        }

                                                        $record->unsetRelation('images');
                                                    })
                                                    ->dehydrated(false),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->collapsible()
                                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                            ->addActionLabel('Add Variant'),
                                    ])
                                    ->visible(fn(callable $get) => $get('has_variants'))
                                    ->columnSpanFull()
                            ]),
                        Tab::make('Settings')
                            ->icon(Heroicon::Cog6Tooth)
                            ->schema([
                                Section::make('Product status')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->required(),
                                        Toggle::make('is_featured')
                                            ->required(),
                                    ])
                                    ->columns(2),
                                Section::make('statistics')
                                    ->schema([
                                        TextEntry::make('views_count')
                                            ->state(fn($record) => $record?->views_count ?? 0),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->state(fn($record) => $record?->created_at?->diffForHumans() ?? '-'),
                                    ])
                            ]),
                    ]),
            ]);
    }
}