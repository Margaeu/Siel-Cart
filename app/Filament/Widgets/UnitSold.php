<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use App\Services\HomepageProductRankingService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What has sold, most units first.
 *
 * A row is one thing a customer bought -- a simple product or one variant --
 * summed over the lines of every completed, paid order. Rows are read from the
 * snapshot each line took at checkout rather than from the catalogue, so a
 * product deleted since still shows what it sold, under the name and SKU it
 * sold under. The whole table is that single grouped query.
 */
class UnitSold extends TableWidget
{
    use HasWidgetShield;

    /**
     * What makes two lines the same row. The ids keep apart different products
     * that happen to share a name. The snapshot columns keep lines apart once a
     * force delete has nulled those ids, so the sizes of a deleted shirt do not
     * collapse into one row. Because every searchable column is in here, a
     * search -- which filters lines before they are summed -- always matches
     * whole rows and never a part of one.
     */
    private const ROW_COLUMNS = [
        'order_items.product_name',
        'order_items.variant_name',
        'order_items.product_sku',
        'order_items.product_id',
        'order_items.product_variant_id',
    ];

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OrderItem::query()
                // Left join rather than the product()/category() relations so
                // a force-deleted product (product_id null) still produces a
                // row instead of dropping out. withTrashed isn't needed here
                // either: an ordinary join already matches a soft-deleted
                // product row, which is what we want -- the same "still shows
                // what it sold under" reasoning as OrderItem::product().
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->select(self::ROW_COLUMNS)
                // Filament needs a key per row. No line belongs to two rows,
                // so the lowest line id in each is unique.
                ->selectRaw('MIN(order_items.id) as id')
                // category_id determines category name, but MySQL's
                // ONLY_FULL_GROUP_BY has no way to know that from a joined
                // table, so it's wrapped in MIN() instead of added to the
                // GROUP BY list.
                ->selectRaw('MIN(categories.name) as category_name')
                ->selectRaw('SUM(order_items.quantity) as units_sold')
                ->selectRaw('SUM(order_items.subtotal) as sales_amount')
                // Through the relationship so soft-deleted orders drop out
                // along with everything that is not yet a finished sale.
                ->whereHas('order', fn (Builder $order): Builder => $order->completed()->paymentStatus('paid'))
                ->groupBy(self::ROW_COLUMNS)
                // MySQL hands sums back as strings and SQLite as floats.
                ->withCasts([
                    'units_sold' => 'integer',
                    'sales_amount' => 'decimal:2',
                ]))
            ->heading('Units Sold')
            ->description('Units and sales from completed, paid orders, by product and variant.')
            // Filament's tiebreaker would order by the line id, which is not
            // grouped on, so MySQL rejects it. The row columns break ties
            // instead, and this runs after any sort the admin picks, so pages
            // stay stable whichever column the table is sorted by.
            ->defaultKeySort(false)
            ->defaultSort(function (Builder $query): Builder {
                $query->orderByDesc('units_sold');

                foreach (self::ROW_COLUMNS as $column) {
                    $query->orderBy($column);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product / Variant')
                    ->description(fn (OrderItem $record): ?string => $record->variant_name)
                    ->searchable(['product_name', 'variant_name']),
                TextColumn::make('product_sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('No SKU'),
                TextColumn::make('category_name')
                    ->label('Category')
                    // Null for a force-deleted product (product_id is null)
                    // rather than an absent category -- every live category
                    // relation is protected by restrictOnDelete().
                    ->placeholder('No category')
                    ->sortable(),
                TextColumn::make('units_sold')
                    ->label('Units Sold')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sales_amount')
                    ->label('Sales Amount')
                    ->money('PHP')
                    ->sortable(),
            ])
            ->filters([
                // Compared by date alone, so an order completed at any time on
                // either boundary day is in range.
                Filter::make('completed_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query
                            ->whereHas('order', fn (Builder $order): Builder => $order->whereDate('completed_at', '>=', $date)))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query
                            ->whereHas('order', fn (Builder $order): Builder => $order->whereDate('completed_at', '<=', $date))))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = Indicator::make('Completed from '.Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = Indicator::make('Completed until '.Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                // Best Seller and Top Pick are not stored flags -- Product has
                // no such column. They're computed live by
                // HomepageProductRankingService from a rolling 7-day window of
                // completed, paid sales (Best Sellers capped at one per
                // category; Top Picks capped at eight store-wide), exactly as the
                // homepage badges them. This filter reuses that same service
                // rather than re-deriving the rule, so it always agrees with
                // what customers currently see badged on the storefront.
                // This table is per variant, so Best Seller narrows further to
                // each winner's top-selling variant: one row per category.
                SelectFilter::make('highlight')
                    ->label('Homepage Highlight')
                    ->options([
                        'best_seller' => 'Best Seller',
                        'top_pick' => 'Top Pick',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        $ranking = app(HomepageProductRankingService::class);

                        if ($value === 'best_seller') {
                            // One row per category: the table has a row per
                            // variant, so the winning product alone would
                            // bring every one of its sizes along.
                            $winners = $ranking->bestSellerVariantIds();

                            return $query->where(function (Builder $lines) use ($winners): void {
                                // Matches nothing when there are no winners.
                                $lines->whereRaw('1 = 0');

                                foreach ($winners as $productId => $variantId) {
                                    $lines->orWhere(fn (Builder $line): Builder => $line
                                        ->where('order_items.product_id', $productId)
                                        ->when(
                                            $variantId === null,
                                            fn (Builder $line): Builder => $line->whereNull('order_items.product_variant_id'),
                                            fn (Builder $line): Builder => $line->where('order_items.product_variant_id', $variantId),
                                        ));
                                }
                            });
                        }

                        return $query->whereIn('order_items.product_id', $ranking->topPicks()->pluck('id'));
                    }),
            ])
            ->emptyStateHeading('No sales to show')
            ->emptyStateDescription('Items are counted once their order is completed and paid. If a search or date range is applied, try clearing it.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
