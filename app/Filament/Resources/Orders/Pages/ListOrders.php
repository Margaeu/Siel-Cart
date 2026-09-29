<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** @var array<string, int>|null */
    private ?array $statusCounts = null;

    private ?int $returnActivityCount = null;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Orders'),

            'pending' => $this->statusTab(
                label: 'Pending',
                statuses: 'pending',
                badgeColor: 'warning',
            ),

            'processing' => $this->statusTab(
                label: 'Processing',
                statuses: 'processing',
                badgeColor: 'info',
            ),

            'ready-for-pickup' => $this->statusTab(
                label: 'Ready for Pickup',
                statuses: 'ready_for_pickup',
                badgeColor: Color::Purple,
            ),

            'completed' => $this->statusTab(
                label: 'Completed',
                statuses: 'completed',
                badgeColor: 'success',
            ),

            'cancelled' => $this->statusTab(
                label: 'Cancelled',
                statuses: 'cancelled',
                badgeColor: 'danger',
            ),

            // Orders with a recorded refund or exchange.
            'returns' => Tab::make('Returns/Refunds')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withReturnActivity())
                ->badge(fn (): int => $this->returnActivityCount ??= OrderResource::getEloquentQuery()->withReturnActivity()->count())
                ->badgeColor('warning'),

        ];
    }

    private function statusTab(
        string $label,
        string|array $statuses,
        string|array $badgeColor,
    ): Tab {
        $statuses = (array) $statuses;

        return Tab::make($label)
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('status', $statuses)
            )
            ->badge(
                fn (): int => array_sum(array_map(
                    fn (string $status): int => $this->statusCounts()[$status] ?? 0,
                    $statuses,
                ))
            )
            ->badgeColor($badgeColor);
    }

    /** @return array<string, int> */
    private function statusCounts(): array
    {
        return $this->statusCounts ??= OrderResource::getEloquentQuery()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
