<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Auth\Concerns\HasAuthLayout;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;

/**
 * The admin "forgot password" page, answering the same way whatever the broker
 * says — the panel's counterpart to the storefront's ForgotPasswordController.
 *
 * Filament's stock page shows the broker status on failure, which names
 * unknown emails (INVALID_USER) and, through RESET_THROTTLED, confirms real
 * ones, since only an existing account can have a recent token. It also left
 * the email in the field on failure but cleared it on success. Here every
 * outcome shows the "sent" message and clears the form.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    use HasAuthLayout;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Enter your admin email to receive a password reset link.';
    }

    protected function getEmailFormComponent(): Component
    {
        return $this->authField(parent::getEmailFormComponent(), 'Email Address', Heroicon::OutlinedEnvelope);
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()->label('Send reset link')->icon(Heroicon::ArrowRight)->iconPosition('after');
    }

    /**
     * Put the way back beneath the submit button. Filament's own login link is
     * the subheading, which this page replaces with the instruction line above,
     * so without this an admin who remembered their password has no exit.
     */
    public function getFormContentComponent(): Component
    {
        return parent::getFormContentComponent()
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions'),
                Actions::make([
                    $this->loginAction()
                        ->label('Back to admin login')
                        ->color('gray'),
                ])
                    ->alignment(Alignment::Center)
                    ->key('login-action'),
            ]);
    }

    public function request(): void
    {
        parent::request();

        $this->form->fill();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }
}
