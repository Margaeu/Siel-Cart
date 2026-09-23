<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // These tabs replace the table's is_active filter. There is no Deleted
    // tab, unlike Customers and Products: Category is not soft-deleted, so a
    // deleted category leaves nothing behind to list.
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Categories'),

            'active' => $this->activityTab(label: 'Active', isActive: true, badgeColor: 'success'),

            'inactive' => $this->activityTab(label: 'Inactive', isActive: false, badgeColor: 'warning'),
        ];
    }

    private function activityTab(string $label, bool $isActive, string $badgeColor): Tab
    {
        return Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', $isActive))
            ->badge(static fn (): int => CategoryResource::getEloquentQuery()->where('is_active', $isActive)->count())
            ->badgeColor($badgeColor);
    }
}
