Hello {{ $notifiable->first_name ?? 'there' }},

We received a request to reset the password for your {{ config('app.name') }} account.

RESET YOUR PASSWORD
{{ $resetUrl }}

This password reset link will expire in {{ $expireMinutes }} minute{{ (int) $expireMinutes === 1 ? '' : 's' }}.

SECURITY NOTE
If you did not request a password reset, no further action is required and your password will remain unchanged.

Cheers,
UBAP Team

Need help? Contact us at ubap@clsu.edu.ph.

(c) {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
