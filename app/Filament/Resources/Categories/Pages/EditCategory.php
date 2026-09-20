<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\RejectsDuplicateCategory;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EditCategory extends EditRecord
{
    use RejectsDuplicateCategory;

    protected static string $resource = CategoryResource::class;

    protected function beforeSave(): void
    {
        /** @var Category $category */
        $category = $this->getRecord();

        $this->rejectDuplicateCategory($category);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicateCategoryValidation($data);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
