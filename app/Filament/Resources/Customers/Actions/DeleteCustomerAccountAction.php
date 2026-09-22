<?php

namespace App\Filament\Resources\Customers\Actions;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * The panel's only way to delete a customer. It replaces Filament's
 * DeleteAction / ForceDeleteAction / RestoreAction on purpose:
 *
 * - a plain soft delete would leave the account's identity and credentials in
 *   place, and RestoreAction would hand the account straight back;
 * - a force delete cascades through orders, reviews and reports.
 *
 * It goes through Customer::deleteAccount(), exactly like the storefront's
 * "Permanently Delete Account" button, so both paths anonymize identically and
 * both refuse while an order is active.
 */
class DeleteCustomerAccountAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Delete account permanently');

        $this->color('danger');

        $this->icon(Heroicon::OutlinedTrash);

        $this->requiresConfirmation();

        $this->modalHeading('Permanently delete this customer account?');

        $this->modalDescription('The customer\'s name, email, phone, date of birth and password are erased and they lose access immediately. This cannot be undone and the account cannot be restored. Their orders, reviews and reports stay on file, shown as "[Deleted User]".');

        $this->modalSubmitActionLabel('Delete permanently');

        // Same Shield permission the generic delete used (Delete:Customer).
        $this->authorize('delete');

        $this->hidden(fn (Customer $record): bool => $record->trashed());

        $this->successNotificationTitle('Customer account permanently deleted');

        $this->successRedirectUrl(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record]));

        $this->action(function (Customer $record): void {
            try {
                $record->deleteAccount();
            } catch (ValidationException) {
                Notification::make()
                    ->danger()
                    ->title('Account not deleted')
                    ->body('This customer has an order that is pending, being processed, or ready for pickup. It must be completed or cancelled first.')
                    ->send();

                return;
            }

            $this->success();
        });
    }
}
