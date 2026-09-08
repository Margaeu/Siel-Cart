<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

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
                            ->email()
                            ->required(),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                        TextInput::make('phone')
                            ->tel()
                            ->default(null),
                    ])
                    ->columns(2),

                Section::make('Password')
                    ->description('Set a password for new customers, or leave both fields empty to keep the current password.')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->revealable(),
                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->same('password')
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (string $operation) => $operation === 'create'),
                    ])
                    ->columns(2),
            ]);
    }
}
