<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Manage Products';
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.dashboard') => 'Administrator',
            ProductResource::getUrl() => 'Products',
            'Manage',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Product'),
        ];
    }

    // These tabs replace the old stat widgets and the table's is_active and
    // Trashed filters. ProductsTable drops the SoftDeletingScope, so each tab
    // states its own deleted_at condition; Active and Inactive exclude deleted
    // products so no product is counted under two tabs.
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Products'),

            'active' => $this->productTab(
                label: 'Active',
                scope: fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                badgeColor: 'success',
            ),

            'inactive' => $this->productTab(
                label: 'Inactive',
                scope: fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', false),
                badgeColor: 'warning',
            ),

            'deleted' => $this->productTab(
                label: 'Deleted',
                scope: fn (Builder $query): Builder => $query->whereNotNull('deleted_at'),
                badgeColor: 'danger',
            ),
        ];
    }

    private function productTab(string $label, Closure $scope, string $badgeColor): Tab
    {
        return Tab::make($label)
            ->modifyQueryUsing($scope)
            ->badge(static fn (): int => $scope(ProductResource::getEloquentQuery()->withTrashed())->count())
            ->badgeColor($badgeColor);
    }
}
