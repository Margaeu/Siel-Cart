<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Theme;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to a customer's NEW address when they request an email change from
 * the profile page. Following the link proves ownership of that address;
 * only then does Profile::confirmEmailChange (via the customer.email.confirm
 * route) move it into the live `email` column. The customer's current
 * address is never touched until this is clicked, so an unconfirmed change
 * never locks anyone out.
 */
class CustomerConfirmEmailChange extends Notification
{
    /**
     * Deliberately short, matching config/auth.php's customer password reset
     * window rather than the 60-minute email-verification default: this
     * link changes a login credential, so it should be just as dead almost
     * immediately if forwarded, left in an open inbox, or read off a shared
     * machine.
     */
    protected const EXPIRE_MINUTES = 3;

    public function __construct(protected Customer $customer) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Signed against the customer id and a hash of the PENDING email (not
     * the current one), the same way Laravel's own VerifyEmail signs
     * against the current email — so the link only ever confirms the
     * exact address it was mailed to.
     */
    protected function confirmationUrl(): string
    {
        return URL::temporarySignedRoute(
            'customer.email.confirm',
            now()->addMinutes(self::EXPIRE_MINUTES),
            [
                'customer' => $this->customer->getKey(),
                'hash' => sha1($this->customer->pending_email),
            ]
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $confirmationUrl = $this->confirmationUrl();

        $theme = Theme::active()->first();

        return (new MailMessage)
            ->subject('Confirm Your New Email Address - '.config('app.name'))
            ->view(
                ['html' => 'mail.auth.confirm-email-change', 'text' => 'mail.auth.confirm-email-change-text'],
                [
                    'notifiable' => $this->customer,
                    'confirmationUrl' => $confirmationUrl,
                    'currentEmail' => $this->customer->email,
                    'newEmail' => $this->customer->pending_email,
                    'primaryColor' => $theme?->primary_color ?? '#1E6031',
                    'secondaryColor' => $theme?->secondary_color ?? '#E0A70D',
                    'expireMinutes' => self::EXPIRE_MINUTES,
                ]
            );
    }
}
