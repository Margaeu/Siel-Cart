<?php

namespace App\Filament\Resources\ReturnRefunds\Pages;

use App\Enums\OrderItemResolutionType;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReturnRefunds extends ListRecords
{
    protected static string $resource = ReturnRefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record refund or exchange'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'exchanges' => $this->typeTab('Exchanges', OrderItemResolutionType::Exchange),

            'refunds' => $this->typeTab('Refunds', OrderItemResolutionType::Refund),
        ];
    }

    /** Badge colour follows the enum so the tab matches the Outcome column. */
    private function typeTab(string $label, OrderItemResolutionType $type): Tab
    {
        return Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', $type))
            ->badge(static fn (): int => ReturnRefundResource::getModel()::query()->where('type', $type)->count())
            ->badgeColor($type->getColor());
    }
}
