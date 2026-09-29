<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        // Erased details of a deleted customer are NULL or internal
        // placeholders; show "Removed" instead of those values.
        $erased = fn (string $column) => fn (Customer $record) => $record->trashed()
            ? Customer::REMOVED_LABEL
            : $record->{$column};

        return $table
            // Deleted accounts are listed too, under the Deleted tab; the tabs
            // on ListCustomers apply the deleted_at condition themselves.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            // Newest first, so an admin sees a record they just created or changed
            // at the top and can confirm the change landed.
            ->defaultSort('created_at', 'desc')
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

                // No is_active column: deactivating a customer is not an admin
                // action. The only path that clears the flag is the customer
                // deleting their own account (Customer::deleteAccount()), and
                // an editable toggle implied the panel could undo that. The
                // state is still reported read-only, under the Name column and
                // on the Account section of the view page.

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
            // No TrashedFilter: the All/Active/Inactive/Deleted tabs on
            // ListCustomers decide which accounts are shown. A TrashedFilter's
            // default "without deleted" would leave the Deleted tab empty.
            ->recordActions([
                ViewAction::make(),
                // Hidden explicitly: EditAction authorizes through the policy,
                // not CustomerResource::canEdit(), which refuses deleted records.
                EditAction::make()
                    ->hidden(fn (Customer $record): bool => $record->trashed()),
                // Hidden for everyone but super admins (and already-deleted
                // rows) by CustomerResource::canDelete(), which backs this
                // action's ->authorize(). See deleteAccountAction() for why
                // it calls Customer::deleteAccount() instead of DeleteAction.
                CustomerResource::deleteAccountAction(),
            ]);
        // No bulk actions: DeleteBulkAction, ForceDeleteBulkAction and
        // RestoreBulkAction would all bypass Customer::deleteAccount(), and
        // force delete cascades through orders, reviews and reports.
    }
}
