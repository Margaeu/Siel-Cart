<?php

namespace App\Notifications;

use App\Models\Theme;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded replacement for the admin panel's "forgot password" mail, so it
 * matches AdminInvitation and CustomerResetPassword instead of Laravel's stock
 * markdown layout.
 *
 * Filament's RequestPasswordReset page resolves its notification through the
 * container (`app(ResetPassword::class, ['token' => ...])`) and then sets
 * `$url` to the panel's signed reset link, so this is swapped in by a binding
 * in AppServiceProvider rather than through User::sendPasswordResetNotification(),
 * which the panel never calls. Extending Filament's class keeps its queueing
 * and its `$url` handling unchanged — only the rendered email differs.
 */
class AdminResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $theme = Theme::active()->first();

        return (new MailMessage)
            ->subject('Reset Your Password - '.config('app.name').' Admin')
            ->view(
                ['html' => 'mail.auth.admin-reset-password', 'text' => 'mail.auth.admin-reset-password-text'],
                [
                    'notifiable' => $notifiable,
                    'resetUrl' => $this->resetUrl($notifiable),
                    'loginUrl' => Filament::getPanel('admin')->getLoginUrl(),
                    'primaryColor' => $theme?->primary_color ?? '#1E6031',
                    // Quote the broker the panel issues tokens under, not
                    // auth.defaults.passwords — see the note in config/auth.php.
                    'expireMinutes' => (int) config('auth.passwords.'.AdminInvitation::BROKER.'.expire'),
                ]
            );
    }
}
