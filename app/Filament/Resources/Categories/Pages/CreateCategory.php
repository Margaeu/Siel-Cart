<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\RejectsDuplicateCategory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateCategory extends CreateRecord
{
    use RejectsDuplicateCategory;

    protected static string $resource = CategoryResource::class;

    protected function beforeCreate(): void
    {
        $this->rejectDuplicateCategory();
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicateCategoryValidation($data);
        }
    }
}
