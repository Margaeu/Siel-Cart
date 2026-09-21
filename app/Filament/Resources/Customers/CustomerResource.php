<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
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

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
