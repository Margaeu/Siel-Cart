<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Notifications\AdminInvitation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Throwable;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 10;

    // Creating an administrator — and resending its invitation, which answers to
    // this same check — is super-admin-only, enforced here rather than by the
    // Shield `Create:User` permission alone: that is a tickbox any role can be
    // given from the Roles screen, and it would let a ubap/stratcom admin mint
    // accounts (including super admins) for themselves. Both must hold.
    //
    // Overridden at the Response level, not canCreate(): the list page's
    // CreateAction asks getCreateAuthorizationResponse() directly, and
    // canCreate() (create page, resend action) is derived from it.
    public static function getCreateAuthorizationResponse(): Response
    {
        $user = Filament::auth()->user();

        if (! ($user instanceof User && $user->hasRole('super_admin'))) {
            return Response::deny('Only super admins can create administrator accounts.');
        }

        return parent::getCreateAuthorizationResponse();
    }

    /**
     * Email $record a fresh invitation, returning whether it went out.
     *
     * A failure is reported and turned into false rather than thrown: by the
     * time this runs the account already exists, and a mail outage must not
     * surface as a 500 over a saved record. The caller shows the admin a
     * notification pointing at "Resend invitation". Nothing here — the
     * exception included — carries a password; the account has none anyone knows.
     */
    public static function sendInvitation(User $record): bool
    {
        try {
            AdminInvitation::issueTo($record);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
