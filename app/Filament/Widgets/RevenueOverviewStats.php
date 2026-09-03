<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Paid Product Revenue (Merchandise Subtotal of Completed Orders)
        $paidProductRevenue = Order::where('status', 'completed')->sum('subtotal');

        // 2. Total Completed Revenue
        $totalRevenue = Order::where('status', 'completed')->sum('total');

        // 3. Completed Orders Count
        $completedCount = Order::where('status', 'completed')->count();

        return [
            Stat::make('Paid Product Revenue', '₱' . number_format($paidProductRevenue, 2))
                ->description('Total merchandise amount collected')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Collected Revenue', '₱' . number_format($totalRevenue, 2))
                ->description('All completed orders total')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Completed Pickups', $completedCount)
                ->description('Orders successfully claimed')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
        ];
    }
}