<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryDeletionGuard;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\RejectsDuplicateCategory;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
            // Check before anything is touched so a blocked delete changes
            // neither the database nor the R2 image. using() then catches the
            // race where a product was assigned after the check and the
            // RESTRICT foreign key refuses the delete.
            DeleteAction::make()
                ->before(function (DeleteAction $action, Category $record): void {
                    if (! $record->hasAssignedProducts()) {
                        return;
                    }

                    CategoryDeletionGuard::notifyBlocked([$record->name]);
                    $action->cancel();
                })
                ->using(function (DeleteAction $action, Category $record): bool {
                    try {
                        return (bool) $record->delete();
                    } catch (QueryException $exception) {
                        if (! CategoryDeletionGuard::isForeignKeyViolation($exception)) {
                            throw $exception;
                        }

                        CategoryDeletionGuard::notifyBlocked([$record->name]);
                        $action->cancel(shouldRollBackDatabaseTransaction: true);
                    }
                }),
        ];
    }
}
