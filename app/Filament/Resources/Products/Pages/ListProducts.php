<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\Products\Widgets\ActiveProductsStat;
use App\Filament\Resources\Products\Widgets\InactiveProductsStat;
use App\Filament\Resources\Products\Widgets\ProductStatsOverview;

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

    protected function getHeaderWidgets(): array
    {
        return [
            ProductStatsOverview::class,
            ActiveProductsStat::class,
            InactiveProductsStat::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return ['default' => 1, 'md' => 3];
    }
}
