<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;
    protected ?string $heading = 'Revenue Chart';
    public ?string $filter = 'week';

    protected function getData(): array
    {
        $activeFilter = $this->filter;
        $data = Trend::model(Order::class)
            ->between(
                start: match ($activeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'year' => now()->subYear(),
                },
                end: now()
            )
            ->perWeek()
            ->sum('total');

        return [
            'dataset' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate)
                ],
            ],
            'labels' => $data->map(fn(TrendValue $value) => $value->date)
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): array|null
    {
        return [
            'week' => 'Last Week',
            'month' => 'Last Month',
            'year' => 'Last Year',
        ];
    }
}
