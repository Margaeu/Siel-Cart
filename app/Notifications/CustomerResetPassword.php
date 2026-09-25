<?php

namespace App\Notifications;

use App\Models\Theme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded replacement for Laravel's default ResetPassword notification.
 * resetUrl() is inherited unchanged, so the token, its expiry, and the
 * password.reset route it points at are exactly what the framework
 * would have produced — only the rendered email differs.
 */
class CustomerResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);

        $theme = Theme::active()->first();

        // Laravel's stock ResetPassword::buildMailMessage() quotes the DEFAULT
        // broker's expiry (auth.defaults.passwords = users). Customer tokens are
        // only ever issued by the "customers" broker (Customer::sendPasswordResetNotification),
        // and the two brokers' lifetimes are allowed to differ, so read the
        // broker the token actually came from.
        $expireMinutes = config('auth.passwords.customers.expire');

        return (new MailMessage)
            ->subject('Reset Your Password - '.config('app.name'))
            ->view(
                ['html' => 'mail.auth.reset-password', 'text' => 'mail.auth.reset-password-text'],
                [
                    'notifiable' => $notifiable,
                    'resetUrl' => $resetUrl,
                    'primaryColor' => $theme?->primary_color ?? '#1E6031',
                    'secondaryColor' => $theme?->secondary_color ?? '#E0A70D',
                    'expireMinutes' => $expireMinutes,
                ]
            );
    }
}
