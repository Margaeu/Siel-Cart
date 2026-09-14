<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
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
                ->select(self::ROW_COLUMNS)
                // Filament needs a key per row. No line belongs to two rows,
                // so the lowest line id in each is unique.
                ->selectRaw('MIN(order_items.id) as id')
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
            ])
            ->emptyStateHeading('No sales to show')
            ->emptyStateDescription('Items are counted once their order is completed and paid. If a search or date range is applied, try clearing it.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
