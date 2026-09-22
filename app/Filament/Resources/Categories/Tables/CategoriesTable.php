<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Resources\Categories\CategoryDeletionGuard;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('r2')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (mixed $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // No is_active filter: the All/Active/Inactive tabs on
            // ListCategories cover it.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // All or nothing. If any selected category still has
                    // products (trashed ones included), nothing is deleted and
                    // the notification names every blocked category, so the
                    // admin never gets a partial result to puzzle over. The
                    // same rule as the edit page, via hasAssignedProducts()'s
                    // withTrashed() relation, in one query for the selection.
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            $blocked = Category::query()
                                ->whereKey($records->modelKeys())
                                ->whereHas('products', fn (Builder $query) => $query->withTrashed())
                                ->orderBy('name')
                                ->pluck('name');

                            if ($blocked->isEmpty()) {
                                return;
                            }

                            CategoryDeletionGuard::notifyBlocked($blocked);
                            $action->cancel();
                        })
                        // One transaction for the batch: if a product is
                        // assigned after the check, the RESTRICT foreign key
                        // fails that delete and the whole batch rolls back.
                        // Image cleanup is deferred to commit, so a rollback
                        // leaves every image in place.
                        ->using(function (DeleteBulkAction $action, Collection $records): void {
                            try {
                                DB::transaction(function () use ($records): void {
                                    $records->each(fn (Category $category) => $category->delete());
                                });
                            } catch (QueryException $exception) {
                                if (! CategoryDeletionGuard::isForeignKeyViolation($exception)) {
                                    throw $exception;
                                }

                                CategoryDeletionGuard::notifyBlocked(
                                    Category::query()
                                        ->whereKey($records->modelKeys())
                                        ->whereHas('products', fn (Builder $query) => $query->withTrashed())
                                        ->pluck('name'),
                                );
                                $action->cancel(shouldRollBackDatabaseTransaction: true);
                            }
                        }),
                ]),
            ]);
    }
}
