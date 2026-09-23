<?php

namespace App\Notifications;

use App\Models\Theme;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

/**
 * Invites a newly created administrator to choose their own password.
 *
 * The creating super admin never picks, sees, or sends a password: the account
 * is saved with a random placeholder nobody knows, and this mail carries a
 * `users`-broker reset token wrapped in the admin panel's signed
 * password-reset URL. Setting a password through that page consumes the token,
 * so the link works once.
 *
 * Queued like Filament's own reset notification. The plaintext token is in the
 * serialized job payload for as long as the job waits, exactly as it would be
 * for a queued Laravel ResetPassword — it expires with the broker regardless.
 */
class AdminInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The broker the invitation token is issued under. Named explicitly rather
     * than read from auth.defaults.passwords so an env override of the default
     * can never route admin invitations through the customers broker.
     */
    public const BROKER = 'users';

    public function __construct(#[SensitiveParameter] public string $token) {}

    /**
     * Issue a fresh token for $admin and email the invitation.
     *
     * createToken() deletes every earlier token for this email before storing
     * the new one, so resending an invitation kills the previous link.
     */
    public static function issueTo(User $admin): void
    {
        $token = Password::broker(self::BROKER)->createToken($admin);

        $admin->notify(new self($token));
    }

    public static function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.self::BROKER.'.expire');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function setupUrl(User $notifiable): string
    {
        // The panel is named, not taken from Filament's "current" panel: a queue
        // worker has none, and the link must land on the admin reset page, never
        // the storefront's password.reset route.
        return Filament::getPanel('admin')->getResetPasswordUrl($this->token, $notifiable);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $theme = Theme::active()->first();

        return (new MailMessage)
            ->subject('You have been invited to the '.config('app.name').' admin panel')
            ->view(
                ['html' => 'mail.auth.admin-invitation', 'text' => 'mail.auth.admin-invitation-text'],
                [
                    'notifiable' => $notifiable,
                    'setupUrl' => $this->setupUrl($notifiable),
                    'loginUrl' => Filament::getPanel('admin')->getLoginUrl(),
                    'primaryColor' => $theme?->primary_color ?? '#1E6031',
                    'expireMinutes' => self::expireMinutes(),
                ]
            );
    }
}
