<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 0;

    protected function getColumns(): array
    {
        return [
            'default' => 1,
            '@sm' => 2,
            '@xl' => 3,
        ];
    }

    protected function getStats(): array
    {
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalProducts = Product::count();
        $totalCustomers = Customer::count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        //$lowStockProducts = Product::lowStock()->count();

        return [
            Stat::make('Pending orders', number_format($pendingOrders))
                ->description('Needs review')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-shopping-cart')
                ->color('warning')
                ->url(route('filament.admin.resources.orders.index', ['tab' => 'pending']))
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--yellow']),
            /*
            Stat::make('Products', number_format($totalProducts))
                ->description('Catalog items')
                ->descriptionIcon('heroicon-m-cube')
                ->icon('heroicon-o-cube')
                ->color('success')
                ->url(route('filament.admin.resources.products.index'))
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--green']),
            */
            Stat::make('Customers', number_format($totalCustomers))
                ->description(number_format($newCustomersThisMonth).' new this month')
                ->descriptionIcon('heroicon-m-user-group')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(route('filament.admin.resources.customers.index'))
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--green']),
            /*
            Stat::make('Low-stock products', number_format($lowStockProducts))
                ->description('Restock required')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(route('filament.admin.resources.products.index'))
                ->extraAttributes(['class' => 'clsu-stat clsu-stat--critical']),
            */
        ];
    }
}
