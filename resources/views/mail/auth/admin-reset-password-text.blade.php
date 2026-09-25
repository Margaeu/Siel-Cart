{{-- Plain-text part: output is not HTML, so values are printed raw. Escaping would turn the reset link's "&" into "&amp;" and break it. --}}
Hello {!! $notifiable->name ?: 'there' !!},

We received a request to reset the password for your {{ config('app.name') }} admin panel account.

RESET YOUR PASSWORD
{!! $resetUrl !!}

This link will expire in {{ $expireMinutes }} minute{{ (int) $expireMinutes === 1 ? '' : 's' }} and can only be used once. If it expires, request a new one from the sign-in page.

Once your password is reset, sign in at {!! $loginUrl !!}

SECURITY NOTE
If you did not request a password reset, no further action is required and your password will remain unchanged.

Cheers,
UBAP Team

Need help? Contact us at ubap@clsu.edu.ph.

(c) {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
