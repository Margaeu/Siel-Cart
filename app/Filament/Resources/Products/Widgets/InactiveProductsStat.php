<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InactiveProductsStat extends StatsOverviewWidget
{
    // One card per widget, laid side by side by ListProducts.
    protected int | string | array $columnSpan = ['default' => 'full', 'md' => 1];

    protected int | array | null $columns = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Inactive products', number_format(Product::where('is_active', false)->count()))
                ->description('Hidden from customers')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--yellow']),
        ];
    }
}
