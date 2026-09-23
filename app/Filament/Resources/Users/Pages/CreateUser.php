<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Whether the invitation mail went out; decides the success toast's wording. */
    protected bool $invitationSent = false;

    /**
     * The form has no password field on create. The account still needs one,
     * so it gets a random placeholder that is hashed by the model cast and then
     * dropped — never shown, logged, or mailed. Nobody can sign in with it; the
     * new admin replaces it through the invitation link.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Str::password(64);

        return $data;
    }

    /**
     * Runs after the record and its roles (saveRelationships) are saved, but
     * still inside the page's transaction when the panel uses one. afterCommit
     * holds the token and the mail back until the account is really there, so a
     * rollback can't leave a live link to a user that doesn't exist.
     */
    protected function afterCreate(): void
    {
        /** @var User $record */
        $record = $this->getRecord();

        DB::afterCommit(function () use ($record): void {
            $this->invitationSent = UserResource::sendInvitation($record);

            if (! $this->invitationSent) {
                Notification::make()
                    ->title('Invitation email not sent')
                    ->body("The account for {$record->email} was created, but the invitation could not be emailed. Use \"Resend invitation\" on the Users list to try again.")
                    ->danger()
                    ->persistent()
                    ->send();
            }
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        $notification = Notification::make()
            ->success()
            ->title('Administrator created');

        return $this->invitationSent
            ? $notification->body("An invitation to set a password has been emailed to {$this->getRecord()->email}.")
            : $notification;
    }
}
