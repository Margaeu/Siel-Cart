<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\CategoryNameMakesASlug;
use App\Rules\UniqueCategoryName;
use App\Rules\UniqueProductName;
use App\Rules\UniqueSku;
use App\Support\Name;
use App\Support\OptimizedImageStorage;
use App\Support\Sku;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                                    // Name and category share the first row.
                                    ->schema([
                                        // Two products with one name are two
                                        // identical-looking listings a customer
                                        // cannot tell apart, and re-adding a
                                        // product that already exists is the
                                        // easiest mistake to make here. The slug
                                        // never caught it: a duplicate name just
                                        // becomes "name-2".
                                        TextInput::make('name')
                                            ->rule(fn (?Product $record) => new UniqueProductName(ignoreProductId: $record?->id))
                                            ->helperText('Shown to customers. Another product cannot already use it.')
                                            ->required(),
                                        // Inactive categories stay selectable so an inactive
                                        // product can be filed under one, but an active product
                                        // cannot (see Product::boot()). Checked here too so the
                                        // message lands under this field.
                                        Select::make('category_id')
                                            ->relationship('category', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->required()
                                            ->rule(static fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                if ($get('is_active') && Category::query()->whereKey($value)->where('is_active', false)->exists()) {
                                                    $fail(Product::INACTIVE_CATEGORY_MESSAGE);
                                                }
                                            })
                                            // The same two checks the Categories
                                            // pages make, because this modal is a
                                            // second way into the same table: a
                                            // duplicate here would split one
                                            // storefront filter into two
                                            // half-filled ones.
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->rule(new CategoryNameMakesASlug)
                                                    ->rule(new UniqueCategoryName),
                                            ])
                                            // RejectsDuplicateCategory's job on the
                                            // Categories pages: turn the unique
                                            // index's exception -- the race left
                                            // when two admins submit the same new
                                            // name at once -- into the same
                                            // sentence, instead of a SQL error in
                                            // the modal. Halt leaves the modal open
                                            // with what the admin typed.
                                            ->createOptionUsing(function (Select $component, array $data, Schema $schema): mixed {
                                                try {
                                                    // Filament's own createOptionUsing, kept
                                                    // verbatim so a field added to the modal
                                                    // later still saves its relationships.
                                                    $record = $component->getRelationship()->newModelInstance();
                                                    $record->fill($data);
                                                    $record->save();

                                                    $schema->model($record)->saveRelationships();

                                                    return $record->getKey();
                                                } catch (UniqueConstraintViolationException) {
                                                    $name = trim((string) ($data['name'] ?? ''));
                                                    $existing = Category::findByGeneratedSlug($name);

                                                    Notification::make()
                                                        ->title('Category already exists')
                                                        ->body(UniqueCategoryName::messageFor($existing->name ?? $name))
                                                        ->warning()
                                                        ->send();

                                                    throw new Halt;
                                                }
                                            }),
                                        // There is deliberately no slug field here. The slug is
                                        // generated from the name at creation and never follows a
                                        // later rename (see Product::boot()), so on edit it was a
                                        // read-only box of jargon staff can't act on. Leaving it
                                        // out of the schema is also what actually protects it:
                                        // ->readOnly() is only an HTML attribute, and a crafted
                                        // Livewire request could still set the column through it.
                                        // The slug is shown, labelled and copyable, on the View
                                        // page (ProductInfolist), which is where someone who wants
                                        // the product's public address goes looking.
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
                                        // Explicit defaults: a toggle with none starts off, so
                                        // every new product used to be saved inactive.
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->helperText('Inactive products are hidden from the storefront.')
                                            ->default(true)
                                            ->required()
                                            ->markAsRequired(false),
                                        Toggle::make('is_featured')
                                            ->label('Feature')
                                            ->helperText('Shows this product in the Featured Products section. Best Sellers and Top Picks are filled by completed sales and do not depend on this setting.')
                                            ->default(false)
                                            ->required()
                                            ->markAsRequired(false),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Pricing & Inventory')
                            ->icon(Heroicon::Banknotes)
                            ->visible(fn (callable $get): bool => ! $get('has_variants'))
                            ->schema([
                                Section::make('Pricing')
                                    ->description('Set the identifier and selling price for this product.')
                                    ->schema([
                                        // Unique across products *and* variants, ignoring case
                                        // and surrounding spaces. This tab is hidden for variant
                                        // products, and hidden fields are neither validated nor
                                        // saved, so these rules bind simple products only.
                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->rule(fn (?Product $record) => new UniqueSku(ignoreProductId: $record?->id))
                                            ->helperText('Stock keeping unit - unique identifier')
                                            ->required(),
                                        TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->type('text')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->helperText('Selling price')
                                            ->prefix('₱'),
                                    ])->columns(2),
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
                                    ->columns(2),
                            ]),
                        Tab::make('Images')
                            ->icon(Heroicon::Photo)
                            ->schema([
                                Section::make('Product Images')
                                    ->description('Shared images shown for every variant — size charts, packaging, model displays. The first image will be the primary image. Per-variant photos are uploaded under the Product Variants tab.')
                                    ->schema([
                                        FileUpload::make('generalImages')
                                            ->helperText('Recommended size: Max 3MB.')
                                            ->disk('r2')
                                            ->visibility('public')
                                            ->label('Product Images')
                                            ->multiple()
                                            ->image()
                                            ->panelLayout('grid')
                                            ->directory('products')
                                            ->maxSize(3072)
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->orientImagesFromExif(false)
                                            ->imagePreviewHeight('250')
                                            ->fetchFileInformation(false)
                                            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                                                return OptimizedImageStorage::store(
                                                    $file,
                                                    $component->getDiskName(),
                                                    $component->getDirectory(),
                                                    $component->getUploadedFileNameForStorage($file),
                                                    maxWidth: 1600,
                                                );
                                            })
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
                                                // Only the row is deleted here: ProductImage's deleted hook
                                                // removes the R2 object after the save commits, so a failed
                                                // save cannot leave a row pointing at a deleted file.
                                                foreach ($record->generalImages()->get() as $existingImage) {
                                                    if (in_array($existingImage->image_path, $paths, true)) {
                                                        continue;
                                                    }

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
                                            ->dehydrated(false),
                                    ]),
                            ]),
                        Tab::make('Product Variants')
                            ->icon(Heroicon::Squares2x2)
                            ->schema([
                                Section::make('Variant setup')
                                    ->description('Choose whether sizes, colors, or other options have their own price and stock.')
                                    ->schema([
                                        // Fixed after creation: converting would leave the parent's
                                        // price, stock, and SKU (or the variants) behind with no
                                        // defined meaning. Disabled fields are not saved, and
                                        // Product::boot() refuses the change from anywhere else.
                                        Toggle::make('has_variants')
                                            ->label('Has variants')
                                            ->live()
                                            ->default(false)
                                            ->disabledOn('edit')
                                            ->required()
                                            ->markAsRequired(false)
                                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                                ? 'Product type is fixed after creation. Create a new product to change it.'
                                                : 'When on, this product is priced and stocked per variant, and the Pricing & Inventory tab is hidden. This cannot be changed after the product is created.'),
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
                                                // The customer picks a variant by this
                                                // name alone, so one product cannot
                                                // offer two rows called "M" -- neither
                                                // choice means anything, and it is
                                                // usually a row typed twice rather than
                                                // a real option. Compared by
                                                // Name::comparisonKey(), so "M", "m" and
                                                // " M " are one name.
                                                //
                                                // Only the rows in this submission are
                                                // compared, with no check against the
                                                // saved siblings: the repeater holds
                                                // every variant the product will have
                                                // after the save, so a row being deleted
                                                // in the same save must not block its
                                                // replacement. A row always matches
                                                // itself once; only a second occurrence
                                                // is a duplicate.
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label('Variant Name')
                                                    ->placeholder('e.g., Red - Large')
                                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                        $key = Name::comparisonKey(is_scalar($value) ? (string) $value : null);

                                                        if ($key === null) {
                                                            return;
                                                        }

                                                        // Relative to this row: up out of the row, then
                                                        // the repeater. An absolute '/variants' would
                                                        // miss the form's `data.` prefix and read null.
                                                        $rows = $get('../../variants');

                                                        $occurrences = collect(is_array($rows) ? $rows : [])
                                                            ->filter(fn ($row): bool => is_array($row)
                                                                && Name::comparisonKey(is_scalar($row['name'] ?? null) ? (string) $row['name'] : null) === $key)
                                                            ->count();

                                                        if ($occurrences > 1) {
                                                            $fail('Another variant already uses this name.');
                                                        }
                                                    })
                                                    ->columnSpan(2),
                                                // Two checks: against every saved product and variant
                                                // (UniqueSku), and against the other rows in this same
                                                // submission, which aren't in the database yet. distinct()
                                                // would only catch exact matches, so the rows are compared
                                                // by Sku::comparisonKey() -- "ABC-001", "abc-001" and
                                                // " ABC-001 " are one SKU. A row always matches itself
                                                // once; only a second occurrence is a duplicate.
                                                TextInput::make('sku')
                                                    ->label('SKU')
                                                    ->rule(fn (?ProductVariant $record) => new UniqueSku(ignoreVariantId: $record?->id))
                                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                        $key = Sku::comparisonKey(is_scalar($value) ? (string) $value : null);

                                                        if ($key === null) {
                                                            return;
                                                        }

                                                        // Relative to this row: up out of the row, then
                                                        // the repeater. An absolute '/variants' would
                                                        // miss the form's `data.` prefix and read null.
                                                        $rows = $get('../../variants');

                                                        $occurrences = collect(is_array($rows) ? $rows : [])
                                                            ->filter(fn ($row): bool => is_array($row)
                                                                && Sku::comparisonKey(is_scalar($row['sku'] ?? null) ? (string) $row['sku'] : null) === $key)
                                                            ->count();

                                                        if ($occurrences > 1) {
                                                            $fail('This SKU is repeated in another variant row.');
                                                        }
                                                    })
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
                                                    ->helperText('Shown when a customer selects this variant. Leave empty to keep showing the shared product images. Recommended size: Max 3MB.')
                                                    ->multiple()
                                                    ->image()
                                                    ->panelLayout('grid')
                                                    ->directory('products/variants')
                                                    ->maxSize(3072)
                                                    ->reorderable()
                                                    ->columnSpanFull()
                                                    ->orientImagesFromExif(false)
                                                    ->imagePreviewHeight('150')
                                                    ->fetchFileInformation(false)
                                                    ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                                                        return OptimizedImageStorage::store(
                                                            $file,
                                                            $component->getDiskName(),
                                                            $component->getDirectory(),
                                                            $component->getUploadedFileNameForStorage($file),
                                                            maxWidth: 1600,
                                                        );
                                                    })
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

                                                        // Row only; the R2 object goes after commit (see above).
                                                        foreach ($record->images()->get() as $existingImage) {
                                                            if (in_array($existingImage->image_path, $paths, true)) {
                                                                continue;
                                                            }

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
                                            // A variant product is priced and stocked only through its
                                            // variants, so saving one with none leaves nothing to sell.
                                            // The section is hidden for simple products, and hidden
                                            // fields are not validated, so this never blocks them.
                                            ->required()
                                            ->minItems(1)
                                            ->validationMessages([
                                                'required' => 'Add at least one variant, or turn off "Has variants".',
                                                'min' => 'Add at least one variant, or turn off "Has variants".',
                                            ])
                                            ->collapsible()
                                            // Without this the drag handle reorders the rows on screen
                                            // and the arrangement is lost on reload, because nothing
                                            // writes the sort_order column the storefront reads.
                                            ->orderColumn('sort_order')
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                            ->addActionLabel('Add Variant'),
                                    ])
                                    ->visible(fn (callable $get) => $get('has_variants'))
                                    ->columnSpanFull(),
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
