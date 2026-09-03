<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\RefundRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $grossRevenue = Order::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalRefunds = RefundRequest::where('status', 'approved')
            ->sum('refund_amount');

        $netRevenue = $grossRevenue - $totalRefunds;

        return [
            Stat::make('Gross Revenue', '₱' . number_format($grossRevenue, 2))
                ->description('Total cash collected from completed pickups')
                ->color('success'),

            Stat::make('Approved Refunds', '₱' . number_format($totalRefunds, 2))
                ->description('Total refunded claims')
                ->color('danger'),

            Stat::make('Net Revenue', '₱' . number_format($netRevenue, 2))
                ->description('Actual profit after refunds')
                ->color('primary'),
        ];
    }
}