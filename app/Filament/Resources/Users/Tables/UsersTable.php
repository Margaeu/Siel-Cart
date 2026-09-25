<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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
                    ->getStateUsing(fn ($record) => $record->getRoleNames()->first() ?? 'User')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'super_admin' => 'success',
                        'ubap' => 'warning',
                        'stratcom' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title())
                    ->toggleable(isToggledHiddenByDefault: false),
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
                // Issues a new users-broker token, which deletes the old one, so an
                // earlier invitation link stops working. Authorized exactly like
                // creating an administrator. Offered only to accounts that could
                // actually use it: the reset page refuses anyone failing
                // canAccessPanel() (inactive, or no panel role).
                //
                // Never on the signed-in admin's own row: they already know their
                // password, and the new token would kill any reset link they had
                // pending. Refused in authorize(), not just hidden, so the action
                // cannot be mounted against one's own record either.
                Action::make('resendInvitation')
                    ->label('Resend invitation')
                    ->icon('heroicon-o-envelope')
                    ->authorize(fn (User $record): bool => UserResource::canCreate()
                        && ! $record->is(Filament::auth()->user()))
                    ->visible(fn (User $record): bool => $record->canAccessPanel(Filament::getPanel('admin')))
                    ->requiresConfirmation()
                    ->modalHeading('Resend invitation')
                    ->modalDescription(fn (User $record): string => "Email {$record->email} a new link to set their password? Any earlier invitation or reset link for this account will stop working. Their current password stays in place until they use the new link.")
                    ->modalSubmitActionLabel('Send invitation')
                    ->action(function (User $record): void {
                        if (! UserResource::sendInvitation($record)) {
                            Notification::make()
                                ->title('Invitation email not sent')
                                ->body('The invitation could not be emailed. Please try again later.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Invitation sent')
                            ->body("A new link to set a password has been emailed to {$record->email}.")
                            ->success()
                            ->send();
                    }),
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
