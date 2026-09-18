<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveProductsStat extends StatsOverviewWidget
{
    // One card per widget, laid side by side by ListProducts.
    protected int | string | array $columnSpan = ['default' => 'full', 'md' => 1];

    protected int | array | null $columns = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Active products', number_format(Product::active()->count()))
                ->description('Visible in the storefront')
                ->descriptionIcon('heroicon-m-eye')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--green']),
        ];
    }
}
