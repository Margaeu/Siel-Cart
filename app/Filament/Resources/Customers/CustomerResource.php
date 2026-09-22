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
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string | UnitEnum | null $navigationGroup = 'Shop Management';
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

    // Filament's generic delete / force-delete / restore are all unsafe for
    // customers: a plain soft delete leaves identity and credentials in place,
    // restore hands a deleted account back, and a force delete cascades through
    // orders, reviews and reports. Admins cannot delete customer accounts at
    // all: the customer deletes their own, through Customer::deleteAccount().
    public static function canDelete(Model $record): bool
    {
        return false;
    }

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
