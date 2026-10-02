<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReportsFiledRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReportsReceivedRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Management';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    // Customer accounts only ever come from storefront registration (Fortify's
    // CreateNewCustomer), which enforces the date-of-birth/minimum-age rule and
    // sends the email verification link. An admin-created customer would skip
    // both, so no admin — super admin included — may create one from the panel.
    // This overrides the policy rather than relying on Create:Customer, because
    // super_admin bypasses Shield permission checks entirely.
    public static function canCreate(): bool
    {
        return false;
    }

    // A deleted customer is an anonymized record kept only so their orders,
    // reviews and reports stay attached. Editing it could put a real email and
    // password back on it and turn it into a working account again, so it is
    // view-only. Overridden here, not in the policy, for the same super_admin
    // reason as canCreate().
    public static function canEdit(Model $record): bool
    {
        return ! $record->trashed() && parent::canEdit($record);
    }

    // Filament's generic DeleteAction is unsafe for customers: a plain soft
    // delete leaves identity and credentials in place, so it is never used
    // here (see deleteAccountAction()). Deleting an account is gated on the
    // Shield `Delete:Customer` permission, read directly rather than through
    // the policy: Filament treats an ability the policy does not define as
    // allowed, so the check must not depend on that method existing.
    // super_admin passes through Shield's bypass. Because this erases a
    // person's identity for good, tick the box only for roles that should be
    // able to do that. An already-deleted account cannot be deleted again.
    public static function canDelete(Model $record): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('Delete:Customer') && ! $record->trashed();
    }

    // No bulk delete: deleteAccountAction() has to check each customer's
    // active orders and report failures individually, which a bulk action
    // cannot do cleanly. Restore and force-delete stay off regardless of
    // role — restore would hand a deleted account back, and a force delete
    // would cascade through orders, reviews and reports.
    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    // The one way a customer account is deleted from the panel: it calls
    // Customer::deleteAccount() — the same anonymize-and-soft-delete the
    // customer's own storefront profile page uses — instead of Filament's
    // generic DeleteAction, which would just soft-delete the row and leave
    // real identity and credentials on it. Shared by CustomersTable's row
    // action and EditCustomer's header action so both stay in sync.
    // $redirectUrl is only given by EditCustomer: once deleted the record is
    // no longer editable (canEdit() refuses trashed records), so it sends the
    // admin back to the index instead of leaving them on a stale edit form.
    // CustomersTable's row action leaves it null so the table just refreshes.
    public static function deleteAccountAction(?string $redirectUrl = null): Action
    {
        return Action::make('delete_account')
            ->label('Delete account')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->authorize(fn (Customer $record): bool => self::canDelete($record))
            ->requiresConfirmation()
            // A customer with an active order cannot be deleted, so asking
            // the admin to confirm would only lead to a refusal. Hiding the
            // modal sends the click straight to the action, whose catch below
            // shows the refusal. deleteAccount() still re-checks under lock.
            // This must be modalHidden(), not a closure passed to
            // requiresConfirmation(): Filament caches that closure's first
            // result, and with confirmation "off" the modal loses its icon,
            // centring and narrow width and renders as a wide rectangle.
            ->modalHidden(fn (Customer $record): bool => $record->hasActiveOrders())
            ->modalHeading('Permanently delete this customer account?')
            ->modalDescription('This erases the customer\'s name, email, phone number and password. It cannot be undone and the account can never be restored. Their orders, reviews and reports are kept for the store\'s records. Blocked while they have a pending, processing, or ready-for-pickup order.')
            ->modalSubmitActionLabel('Delete account')
            ->successRedirectUrl($redirectUrl)
            ->action(function (Customer $record, Action $action): void {
                try {
                    $record->deleteAccount();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No, this customer cannot be deleted.')
                        // The model's message is written for the customer
                        // ("your account"), so it is not shown to the admin.
                        // 'account' is only ever thrown for an active order.
                        ->body('You cannot delete this customer account because they have an order that is pending, being processed, or ready for pickup.')
                        ->danger()
                        ->send();

                    // Stops here instead of falling through, so the
                    // successRedirectUrl above never fires for a failed
                    // deletion.
                    $action->halt();
                }

                Notification::make()
                    ->title('Account deleted')
                    ->body('The customer\'s identity and credentials were erased. Their orders, reviews and reports remain on record.')
                    ->success()
                    ->send();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    // Deleted customers must stay openable so admins can inspect their retained
    // history; without this the soft-delete scope 404s their view page.
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            ReviewsRelationManager::class,
            ReportsFiledRelationManager::class,
            ReportsReceivedRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
