<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Customer;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
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
                            // `users.email` has a unique index, but without a form rule a
                            // duplicate only surfaced as a raw SQL integrity error on save.
                            // ignoreRecord lets an edit keep the account's own address.
                            ->unique(ignoreRecord: true)
                            ->rule(Rule::unique(Customer::class, 'email'))
                            ->validationMessages([
                                'unique' => 'An account with this email address already exists.',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Access and security')
                    ->description(fn (string $operation): string => $operation === 'create'
                        ? 'Choose what this administrator can manage. They will be emailed a link to set their own password.'
                        : 'Set the sign-in password and choose what this administrator can manage.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            // Never shown on create: a new administrator chooses their
                            // own password from the AdminInvitation link, and CreateUser
                            // stores an unknown random placeholder until then. On edit,
                            // a blank field keeps the current password, so it is left
                            // out of the save instead of wiping it.
                            ->hiddenOn('create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Leave blank to keep the current password.'),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            // An account with no role fails canAccessPanel(), and the
                            // reset page refuses such accounts, so an invitation to a
                            // role-less admin would be a dead link.
                            ->required(fn (string $operation): bool => $operation === 'create')
                            // The default relationship save is a raw pivot sync that
                            // leaves no trace in the activity log; role changes are
                            // privilege changes and must be audited.
                            ->saveRelationshipsUsing(static fn (User $record, ?array $state) => $record->syncRolesAndLog($state ?? []))
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
