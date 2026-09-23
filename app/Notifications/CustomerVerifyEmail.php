<?php

namespace App\Notifications;

use App\Models\Theme;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded replacement for Laravel's default VerifyEmail notification.
 * verificationUrl() is inherited unchanged, so the signed URL, its
 * expiry, and the Fortify route it points at are exactly what the
 * framework would have produced — only the rendered email differs.
 */
class CustomerVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $theme = Theme::active()->first();

        return (new MailMessage)
            ->subject('Verify Your Email Address - '.config('app.name'))
            ->view(
                ['html' => 'mail.auth.verify-email', 'text' => 'mail.auth.verify-email-text'],
                [
                    'notifiable' => $notifiable,
                    'verificationUrl' => $verificationUrl,
                    'primaryColor' => $theme?->primary_color ?? '#1E6031',
                    'secondaryColor' => $theme?->secondary_color ?? '#E0A70D',
                    'expireMinutes' => config('auth.verification.expire', 60),
                ]
            );
    }
}
