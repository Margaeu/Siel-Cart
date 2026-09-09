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

            'returns' => $this->statusTab(
                label: 'Returns/Refunds',
                statuses: ['return_requested', 'return_completed'],
                badgeColor: 'warning',
            ),

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
                static fn (): int => OrderResource::getEloquentQuery()
                    ->whereIn('status', $statuses)
                    ->count()
            )
            ->badgeColor($badgeColor);
    }
}
