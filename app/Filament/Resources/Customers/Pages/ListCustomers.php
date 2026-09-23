<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    // No CreateAction: customers register themselves on the storefront and are
    // never created from the panel. See CustomerResource::canCreate().
    protected function getHeaderActions(): array
    {
        return [];
    }

    // These tabs replace the old TrashedFilter. CustomersTable drops the
    // SoftDeletingScope, so each tab states its own deleted_at condition. A deleted account is also is_active = false, so Inactive must
    // exclude deleted rows or every deleted account would be counted twice.
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Customers'),

            'active' => $this->customerTab(
                label: 'Active',
                scope: fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                badgeColor: 'success',
            ),

            'inactive' => $this->customerTab(
                label: 'Inactive',
                scope: fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', false),
                badgeColor: 'warning',
            ),

            'deleted' => $this->customerTab(
                label: 'Deleted',
                scope: fn (Builder $query): Builder => $query->whereNotNull('deleted_at'),
                badgeColor: 'danger',
            ),
        ];
    }

    private function customerTab(string $label, Closure $scope, string $badgeColor): Tab
    {
        return Tab::make($label)
            ->modifyQueryUsing($scope)
            ->badge(static fn (): int => $scope(CustomerResource::getEloquentQuery()->withTrashed())->count())
            ->badgeColor($badgeColor);
    }
}
