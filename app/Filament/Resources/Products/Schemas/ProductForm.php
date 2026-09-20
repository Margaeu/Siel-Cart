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
                                Section::make('Product details')
                                    ->description('Set the product name and the category customers will browse.')
                                    // Name and category share the first row. The slug only
                                    // exists on edit, so it sits on its own full-width row
                                    // below rather than pushing category onto a half-empty
                                    // second row next to nothing.
                                    ->schema([
                                        TextInput::make('name')
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
                                        TextInput::make('slug')
                                            ->unique(ignoreRecord: true)
                                            ->visible(fn(string $operation) => $operation === 'edit')
                                            ->helperText('Used in the product page address.')
                                            ->required(),
                                    ])->columns(2),
                                Section::make('Product description')
                                    ->description('Add a concise summary and the full details shown on the product page.')
                                    ->schema([
                                        Textarea::make('short_description')
                                            ->default(null)
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        RichEditor::make('description')
                                            ->default(null)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Product status')
                                    ->description('Control whether this item is available and highlighted in the storefront.')
                                    // A toggle always holds true or false, so the required
                                    // asterisk is noise; the rule itself is kept.
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->helperText('Inactive products are hidden from the storefront.')
                                            ->required()
                                            ->markAsRequired(false),
                                        Toggle::make('is_featured')
                                            ->label('Featured')
                                            ->helperText('Featured products are shown on the home page.')
                                            ->required()
                                            ->markAsRequired(false),
                                    ])
                                    ->columns(2)
                            ]),
                        Tab::make('Pricing & Inventory')
                            ->icon(Heroicon::Banknotes)
                            ->visible(fn (callable $get): bool => ! $get('has_variants'))
                            ->schema([
                                Section::make('Pricing')
                                    ->description('Set the identifier and selling price for this product.')
                                    ->schema([
                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Stock keeping unit - unique identifier')
                                            ->required(),
                                        TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            // numeric()/integer() render type="number", whose spinner
                                            // arrows and mouse-wheel stepping let a price or stock count
                                            // change by accident while scrolling the form. A text input
                                            // is typed-only; the numeric/integer/min rules still validate
                                            // server-side, and inputMode keeps the numeric mobile keypad.
                                            ->type('text')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->helperText('Selling price')
                                            ->prefix('₱'),
                                    ])->columns(2),
                                // Labels and hints match the variant card in the Product
                                // Variants tab, so the same field reads the same either way.
                                Section::make('Inventory')
                                    ->description('Track available units and choose when the dashboard should flag low stock.')
                                    ->schema([
                                        TextInput::make('stock_quantity')
                                            ->label('Stock Quantity')
                                            ->required()
                                            ->integer()
                                            ->type('text')
                                            ->minValue(0)
                                            ->helperText('Stock status is calculated from this quantity.')
                                            ->default(0),
                                        TextInput::make('low_stock_threshold')
                                            ->label('Low Stock Alert Threshold')
                                            ->required()
                                            ->integer()
                                            ->type('text')
                                            ->minValue(0)
                                            ->default(10)
                                            ->helperText('Flag at or below this number. Set to 0 to disable.'),
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
                                            ->panelLayout('grid')
                                            ->directory('products')
                                            ->maxSize(2048)
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->orientImagesFromExif(false)
                                            ->imagePreviewHeight('250')
                                            ->fetchFileInformation(false)
                                            ->extraAttributes([
                                                'class' => 'clsu-image-upload',
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
                                Section::make('Variant setup')
                                    ->description('Choose whether sizes, colors, or other options have their own price and stock.')
                                    ->schema([
                                        Toggle::make('has_variants')
                                            ->label('Has variants')
                                            ->live()
                                            ->required()
                                            ->markAsRequired(false)
                                            ->helperText('When on, this product is priced and stocked per variant, and the Pricing & Inventory tab is hidden.'),
                                    ]),
                                Section::make('Product Variants')
                                    ->description('Enter stock for each size or color. The product is in stock when at least one active variant has stock.')
                                    ->schema([
                                        // Three-column card: identity (name + SKU) on the first row,
                                        // the three numbers side by side on the second, then the
                                        // Active toggle and images on their own full-width rows so
                                        // the toggle never sits beside a text input.
                                        Repeater::make('variants')
                                            ->relationship('variants')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label('Variant Name')
                                                    ->placeholder('e.g., Red - Large')
                                                    ->columnSpan(2),
                                                TextInput::make('sku')
                                                    ->label('SKU')
                                                    ->unique(ignoreRecord: true)
                                                    ->helperText('Stock keeping unit - unique identifier')
                                                    ->required(),
                                                TextInput::make('price')
                                                    ->required()
                                                    ->numeric()
                                                    // Typed-only, same as the Pricing & Inventory tab.
                                                    ->type('text')
                                                    ->prefix('₱')
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->helperText('Selling price'),
                                                TextInput::make('stock_quantity')
                                                    ->label('Stock Quantity')
                                                    ->required()
                                                    ->integer()
                                                    ->type('text')
                                                    ->minValue(0)
                                                    ->helperText('Stock status is calculated from this quantity.')
                                                    ->default(0),
                                                TextInput::make('low_stock_threshold')
                                                    ->label('Low Stock Alert Threshold')
                                                    ->required()
                                                    ->integer()
                                                    ->type('text')
                                                    ->minValue(0)
                                                    ->default(10)
                                                    ->helperText('Flag at or below this number. Set to 0 to disable.'),
                                                Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->helperText('Inactive variants are hidden from the storefront.')
                                                    ->default(true)
                                                    ->columnSpanFull(),
                                                FileUpload::make('images')
                                                    ->disk('r2')
                                                    ->visibility('public')
                                                    ->label('Variant Images')
                                                    ->helperText('Shown when a customer selects this variant. Leave empty to keep showing the shared product images.')
                                                    ->multiple()
                                                    ->image()
                                                    ->panelLayout('grid')
                                                    ->directory('products/variants')
                                                    ->maxSize(2048)
                                                    ->reorderable()
                                                    ->columnSpanFull()
                                                    ->orientImagesFromExif(false)
                                                    ->imagePreviewHeight('150')
                                                    ->fetchFileInformation(false)
                                                    ->extraAttributes([
                                                        'class' => 'clsu-image-upload',
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
                                            ->columns(3)
                                            ->defaultItems(0)
                                            ->collapsible()
                                            // Without this the drag handle reorders the rows on screen
                                            // and the arrangement is lost on reload, because nothing
                                            // writes the sort_order column the storefront reads.
                                            ->orderColumn('sort_order')
                                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                            ->addActionLabel('Add Variant'),
                                    ])
                                    ->visible(fn(callable $get) => $get('has_variants'))
                                    ->columnSpanFull()
                            ]),                                                 
                                /*
                                Section::make('Statistics')
                                    ->description('Read-only activity information for this product.')
                                    ->schema([
                                        TextEntry::make('views_count')
                                            ->state(fn($record) => $record?->views_count ?? 0),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->state(fn($record) => $record?->created_at?->diffForHumans() ?? '-'),
                                    ])
                                */
                    ]),
            ]);
    }
}
