<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer details')
                    ->description('Contact and verification information used for orders and account access.')
                    ->schema([
                        TextInput::make('first_name')
                            ->required(),
                        TextInput::make('last_name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->unique(ignoreRecord: true)
                            ->rule(Rule::unique(User::class, 'email'))
                            ->email()
                            ->required(),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                        DatePicker::make('date_of_birth')
                            ->label('Birthdate')
                            // Mirrors CreateNewCustomer's registration rule: an
                            // admin edit can't push a customer under the
                            // 13-year minimum age any more than the customer
                            // could at signup. maxDate() both disables the
                            // picker past this date and adds the matching
                            // before_or_equal validation rule.
                            ->maxDate(fn () => now()->subYears(13)->startOfDay())
                            ->validationMessages([
                                'before_or_equal' => 'The customer must be at least 13 years old.',
                            ]),
                        TextInput::make('phone')
                            ->tel()
                            ->default(null),
                    ])
                    ->columns(2),

                // Edit-only: this form has no create operation (see
                // CustomerResource::canCreate()), so the password is always a
                // reset of an existing one and both fields stay optional.
                Section::make('Password')
                    ->description('Leave both fields empty to keep the current password.')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable(),
                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->same('password')
                            ->revealable()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }
}
