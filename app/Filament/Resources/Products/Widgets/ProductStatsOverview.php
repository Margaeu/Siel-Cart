<?php

namespace App\Filament\Resources\Products\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Product;

class ProductStatsOverview extends StatsOverviewWidget
{
    // One card per widget, laid side by side by ListProducts.
    protected int | string | array $columnSpan = ['default' => 'full', 'md' => 1];

    protected int | array | null $columns = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Products', number_format(Product::count()))
                ->description('Catalog items')
                ->icon('heroicon-o-cube')
                ->color('success')
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--green']),
        ];
    }
}
