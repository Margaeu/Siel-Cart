<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account details')
                    ->description('Basic information used to identify this administrator.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->required(),
                        TextInput::make('last_name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Access and security')
                    ->description('Set the sign-in password and choose what this administrator can manage.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            // A password is only mandatory when creating an account.
                            // On edit, a blank field keeps the current password, so
                            // it is left out of the save instead of wiping it.
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            // Dropping the super admin role locks an account out of
                            // the panel exactly like deactivating it, so it answers
                            // to the same guard as the toggle and the delete action.
                            ->rule(static fn (?User $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (! $record?->hasRole('super_admin')) {
                                    return;
                                }

                                $superAdminId = Role::where('name', 'super_admin')->value('id');

                                $isKeepingRole = collect($value)
                                    ->contains(fn (mixed $roleId): bool => (int) $roleId === (int) $superAdminId);

                                if ($isKeepingRole) {
                                    return;
                                }

                                $actor = Filament::auth()->user();

                                if ($record->canLosePanelAccessBy($actor)) {
                                    return;
                                }

                                $fail($record->is($actor)
                                    ? 'You cannot remove your own super admin role.'
                                    : 'This is the last active super admin — removing the role would leave the panel with no full-access account.');
                            }),
                    ]),
            ]);
    }
}
