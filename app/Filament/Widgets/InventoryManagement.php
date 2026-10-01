<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * What is on the shelves, worst first.
 *
 * A row is one thing that can run out rather than one product: see
 * InventoryItem for how simple products and the variants of variable products
 * are brought together. The whole table is that single query, so the row count
 * never changes the query count.
 */
class InventoryManagement extends TableWidget
{
    use HasWidgetShield;

    /**
     * Mark the rows that need attention, tinting each one to match its status
     * badge. The widget's view styles these; nothing in Filament's stylesheet
     * does.
     */
    public const OUT_OF_STOCK_ROW_CLASS = 'fi-inventory-out-of-stock';

    public const LOW_STOCK_ROW_CLASS = 'fi-inventory-low-stock';

    protected static ?int $sort = 2;

    // Eager, like the Design Overview widgets and ActivityLogStats. Widget::$isLazy
    // defaults to true, which makes this widget its own Livewire round trip
    // after first paint, and each of those re-pays the panel's session, auth
    // and role/permission lookups against Aiven. Eager renders it in the
    // dashboard's own request.
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.inventory-management';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InventoryItem::query())
            ->heading('Inventory')
            ->description('Stock levels across products and their variants.')
            // A row is keyed by which table it came from plus that row's id,
            // which is an accessor rather than a column. Filament's automatic
            // tiebreaker on the primary key would look for it in the union.
            ->defaultKeySort(false)
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('status_rank')
                ->orderBy('product_name')
                ->orderBy('variant_name')
                ->orderBy('source_id'))
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product / Variant')
                    // Variants read as lines under the product they belong to,
                    // which is also how the admin thinks about them.
                    ->description(fn (InventoryItem $record): ?string => $record->variant_name)
                    ->searchable(['product_name', 'variant_name'])
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No SKU'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryItem::STATUS_LABELS[$state])
                    ->color(fn (string $state): string => match ($state) {
                        InventoryItem::STATUS_OUT_OF_STOCK => 'danger',
                        InventoryItem::STATUS_LOW_STOCK => 'warning',
                        default => 'success',
                    })
                    // The stored value sorts alphabetically, which is not the
                    // order anyone restocks in.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status_rank', $direction)),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('low_stock_threshold')
                    ->label('Low Stock Threshold')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(InventoryItem::STATUS_LABELS),
                TernaryFilter::make('is_active')
                    ->label('Availability')
                    ->trueLabel('On sale')
                    ->falseLabel('Not on sale')
                    ->placeholder('All')
                    ->default(true),
            ])
            ->recordClasses(fn (InventoryItem $record): ?string => match ($record->status) {
                InventoryItem::STATUS_OUT_OF_STOCK => self::OUT_OF_STOCK_ROW_CLASS,
                InventoryItem::STATUS_LOW_STOCK => self::LOW_STOCK_ROW_CLASS,
                default => null,
            });
    }
}
