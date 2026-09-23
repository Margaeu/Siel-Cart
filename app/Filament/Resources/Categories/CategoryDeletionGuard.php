<?php

namespace App\Filament\Resources\Categories;

use App\Models\Category;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;

/**
 * Shared by the edit-page delete and the bulk delete so both explain a
 * blocked delete the same way. The decision itself is
 * Category::hasAssignedProducts(); the RESTRICT foreign key on
 * products.category_id is the final authority behind it.
 */
final class CategoryDeletionGuard
{
    /**
     * @param  iterable<string>  $names
     */
    public static function notifyBlocked(iterable $names): void
    {
        $names = collect($names)->filter()->unique()->values();

        $title = $names->count() === 1
            ? "\"{$names->first()}\" can't be deleted"
            : "{$names->count()} categories can't be deleted";

        $body = $names->count() === 1
            ? Category::ASSIGNED_PRODUCTS_MESSAGE
            : 'Nothing was deleted. '.$names->join(', ', ' and ').' still have products assigned, including any that are inactive or deleted. Reassign those products to another category or permanently delete them first.';

        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * A product can be assigned between the pre-check and the delete. The
     * database then refuses with an integrity-constraint error (SQLSTATE
     * 23000 on MySQL; SQLite reports "FOREIGN KEY constraint failed").
     */
    public static function isForeignKeyViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed')
            || str_contains($exception->getMessage(), 'foreign key constraint fails');
    }
}
