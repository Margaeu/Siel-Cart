<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\Actions\DeleteCustomerAccountAction;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        // Erased details of a deleted customer are NULL or internal
        // placeholders; show the label instead of those values.
        $erased = fn (string $column) => fn (Customer $record) => $record->trashed()
            ? Customer::DELETED_LABEL
            : $record->{$column};

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    // deleted_at, not the email, is the authoritative deleted state.
                    ->description(function (Customer $record): ?string {
                        if ($record->trashed()) {
                            return 'Permanently deleted';
                        }

                        if (! $record->is_active) {
                            return 'Deactivated Account';
                        }

                        return null;
                    }),

                TextColumn::make('email')
                    ->label('Email address')
                    ->state($erased('email'))
                    ->searchable(),

                TextColumn::make('phone')
                    ->state($erased('phone'))
                    ->searchable(),

                TextColumn::make('date_of_birth')
                    ->state($erased('date_of_birth'))
                    ->label('Birthdate'),

                // A deleted account stays inactive; flipping this would not
                // bring it back, but it must not look like it could.
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Customer $record): bool => $record->trashed()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label('Deleted at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Deleted accounts'),
            ])
            ->recordActions([
                ViewAction::make(),
                // Hidden explicitly: EditAction authorizes through the policy,
                // not CustomerResource::canEdit(), which refuses deleted records.
                EditAction::make()
                    ->hidden(fn (Customer $record): bool => $record->trashed()),
                DeleteCustomerAccountAction::make(),
            ]);
            // No bulk actions: DeleteBulkAction, ForceDeleteBulkAction and
            // RestoreBulkAction would all bypass Customer::deleteAccount(), and
            // force delete cascades through orders, reviews and reports.
    }
}
