<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->getStateUsing(fn($record) => $record->getRoleNames()->first() ?? 'User')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'success',
                        'ubap' => 'warning',
                        'stratcom' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => str($state)->replace('_',' ')->title())
                    ->toggleable(isToggledHiddenByDefault: false),
                    
                TextColumn::make('phone')
                    ->searchable(),
                /*
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                */
                ToggleColumn::make('is_active')
                    ->label('Active')
                    // `disabled()` is re-evaluated server-side before the write,
                    // so this is the authorization check, not just a UI hint.
                    ->disabled(fn (User $record): bool => $record->is_active
                        && ! $record->canLosePanelAccessBy(Filament::auth()->user()))
                    ->tooltip(fn (User $record): ?string => $record->is_active
                        ? $record->panelAccessLossBlockedReason(Filament::auth()->user(), 'deactivate')
                        : null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Without this the bulk action only checks `deleteAny`, which
                    // would let a selection sweep up protected super admins.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
