<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // Same rule as the Active toggle in UsersTable: no deleting yourself
            // and no deleting the last active super admin. It has to be enforced
            // here, not in UserPolicy: Shield intercepts the gate *before* the
            // policy for super_admin, so a policy check never stops the very
            // role that can delete administrators. The before() hook re-checks at
            // submit time, so a stale page can't slip past a hidden button.
            DeleteAction::make()
                ->hidden(fn (User $record): bool => ! $record->canLosePanelAccessBy(Filament::auth()->user()))
                ->modalHeading(fn (User $record): string => "Delete {$record->name}?")
                ->modalDescription(fn (User $record): string => "{$record->email} will lose access to the admin panel immediately and the account cannot be restored. Their past activity stays in the activity log. To keep the account but block sign-in, turn off \"Active\" instead.")
                ->before(function (DeleteAction $action, User $record): void {
                    $reason = $record->panelAccessLossBlockedReason(Filament::auth()->user(), 'delete');

                    if ($reason === null) {
                        return;
                    }

                    Notification::make()
                        ->title('Administrator not deleted')
                        ->body($reason)
                        ->danger()
                        ->send();

                    $action->cancel();
                }),
        ];
    }
}
