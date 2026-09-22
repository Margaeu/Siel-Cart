<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->schema([
                        TextEntry::make('account_status')
                            ->label('Status')
                            ->badge()
                            // deleted_at is the authoritative deleted state.
                            ->state(fn (Customer $record): string => match (true) {
                                $record->trashed() => 'Permanently deleted',
                                ! $record->is_active => 'Deactivated',
                                default => 'Active',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Permanently deleted' => 'danger',
                                'Deactivated' => 'warning',
                                default => 'success',
                            }),
                        TextEntry::make('id')
                            ->label('Customer ID'),
                        TextEntry::make('created_at')
                            ->label('Account created')
                            ->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Account deleted')
                            ->dateTime()
                            ->visible(fn (Customer $record): bool => $record->trashed()),
                    ])
                    ->columns(2),

                // Every detail below is erased on deletion. The stored values are
                // NULL or internal placeholders, so each entry shows the label
                // instead of whatever is left in the column.
                Section::make('Customer details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email address')
                            ->state(self::erased(fn (Customer $record) => $record->email)),
                        TextEntry::make('phone')
                            ->state(self::erased(fn (Customer $record) => $record->phone))
                            ->placeholder('Not provided'),
                        TextEntry::make('date_of_birth')
                            ->label('Birthdate')
                            ->state(self::erased(fn (Customer $record) => $record->date_of_birth
                                ? Carbon::parse($record->date_of_birth)->format('M j, Y')
                                : null))
                            ->placeholder('Not provided'),
                        TextEntry::make('email_verified_at')
                            ->label('Email verified at')
                            ->state(self::erased(fn (Customer $record) => $record->email_verified_at?->format('M j, Y g:i A')))
                            ->placeholder('Not verified'),
                    ])
                    ->columns(2),
            ]);
    }

    private static function erased(Closure $value): Closure
    {
        return fn (Customer $record) => $record->trashed() ? Customer::DELETED_LABEL : $value($record);
    }
}
