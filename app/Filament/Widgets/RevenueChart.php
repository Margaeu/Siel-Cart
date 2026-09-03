<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    // Fix: Remove 'static' from $heading
    protected ?string $heading = 'Revenue Trend (Completed Orders)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Group completed order revenue by month for the current year
        $monthlyRevenue = Order::where('status', 'completed')
            ->whereYear('completed_at', now()->year)
            ->selectRaw('MONTH(completed_at) as month, SUM(total) as aggregate')
            ->groupBy('month')
            ->pluck('aggregate', 'month')
            ->all();

        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = $monthlyRevenue[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label'       => 'Revenue (PHP)',
                    'data'        => $data,
                    'borderColor' => '#1E6031',
                    'fill'        => 'start',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}