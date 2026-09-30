<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Auth\Concerns\HasAuthLayout;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * The admin panel's reset page, which serves two different links: the
 * "forgot password" mail and the AdminInvitation "Set your password" mail. A
 * new admin has no password to reset, so an invitation link says "Set" in the
 * title, heading, button, and success toast; a forgot-password link keeps
 * Filament's "Reset" wording.
 *
 * The page cannot tell the two apart from the token (both come from the same
 * `users` broker), so AdminInvitation::setupUrl() adds `invitation=1`. The URL
 * is signed, so the flag cannot be added to or stripped from a real link
 * without breaking the signature — and it only ever changes wording anyway.
 */
class ResetPassword extends BaseResetPassword
{
    use HasAuthLayout;

    #[Locked]
    public bool $isInvitation = false;

    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null, ?bool $invitation = null): void
    {
        parent::mount($email, $token);

        $this->isInvitation = $invitation ?? request()->boolean('invitation');

        // Filament's own mount() never checks the token - it renders the form
        // either way and only reveals an expired/consumed link as a toast after
        // the admin fills it in and submits. The panel's reset URL is a
        // permanently-signed one (see AdminInvitation::setupUrl()), so an old
        // link never fails signature verification either; the only thing that
        // actually expires is the `users`-broker token row. Checking it here,
        // before the form ever renders, gives admins and invitees the same
        // branded "link expired" page a customer gets, instead of a page that
        // looks usable until they've typed a new password and been told no.
        $resolvedEmail = $email ?? request()->query('email');
        $resolvedToken = $token ?? request()->query('token');

        $broker = Password::broker(Filament::getPanel('admin')->getAuthPasswordBroker());
        $user = $resolvedEmail ? $broker->getUser(['email' => $resolvedEmail]) : null;

        if (! $resolvedToken || ! $user || ! $broker->tokenExists($user, $resolvedToken)) {
            abort(response()->view('errors.link-expired', self::expiredLinkViewData($this->isInvitation), 419));
        }
    }

    /**
     * Shared with bootstrap/app.php's InvalidSignatureException handler, which
     * renders the same page for a tampered (rather than merely expired) link on
     * this same route — one copy of the wording for both failure paths.
     *
     * @return array<string, string|null>
     */
    public static function expiredLinkViewData(bool $isInvitation): array
    {
        $loginUrl = Filament::getPanel('admin')->getLoginUrl();

        if ($isInvitation) {
            return [
                'title' => 'Invitation Expired',
                'heading' => 'This invitation has expired',
                'message' => 'Invitation links expire quickly for security. Ask a super admin to resend your invitation.',
                'primaryLabel' => 'Back to Sign In',
                'primaryUrl' => $loginUrl,
            ];
        }

        return [
            'title' => 'Reset Link Expired',
            'heading' => 'This reset link has expired',
            'message' => 'For your security, password reset links expire quickly. Request a new one to continue.',
            'primaryLabel' => 'Request a new reset link',
            'primaryUrl' => Filament::getPanel('admin')->getRequestPasswordResetUrl(),
            'secondaryLabel' => 'Back to Sign In',
            'secondaryUrl' => $loginUrl,
        ];
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        // The parent titles its success toast with __(Password::PASSWORD_RESET)
        // and offers no hook to change it. Overriding the line for this one
        // request is narrower than copying the whole reset method, which would
        // then stop following Filament's fixes.
        if ($this->isInvitation) {
            Lang::addLines([Password::PASSWORD_RESET => 'Your password has been set.'], app()->getLocale());
        }

        return parent::resetPassword();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isInvitation ? 'Set your password' : parent::getTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->isInvitation ? 'Set your password' : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->isInvitation
            ? 'Choose a password to activate your admin access.'
            : 'Choose a new password for your admin account.';
    }

    protected function getEmailFormComponent(): Component
    {
        return $this->authField(parent::getEmailFormComponent(), 'Email Address', Heroicon::OutlinedEnvelope)
            ->autofocus(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return $this->authField(parent::getPasswordFormComponent(), 'New password', Heroicon::OutlinedLockClosed)
            ->autofocus();
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return $this->authField(parent::getPasswordConfirmationFormComponent(), 'Confirm password', Heroicon::OutlinedLockClosed);
    }

    public function getResetPasswordFormAction(): Action
    {
        $action = parent::getResetPasswordFormAction()->icon(Heroicon::ArrowRight)->iconPosition('after');

        return $this->isInvitation ? $action->label('Set password') : $action;
    }
}
