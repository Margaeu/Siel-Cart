{{-- Plain-text part: output is not HTML, so values are printed raw. Escaping would turn the setup link's "&" into "&amp;" and break it. --}}
Hello {!! $notifiable->name ?: 'there' !!},

An administrator account has been created for you on the {{ config('app.name') }} admin panel.

YOUR ACCOUNT
Sign-in email: {!! $notifiable->email !!}
No password has been set for you. You choose your own below.

SET YOUR PASSWORD
{!! $setupUrl !!}

This link will expire in {{ $expireMinutes }} minute{{ (int) $expireMinutes === 1 ? '' : 's' }} and can only be used once. If it expires, ask a super admin to resend your invitation.

Once your password is set, sign in at {!! $loginUrl !!}

SECURITY NOTE
If you were not expecting this invitation, you can ignore this email. No one can sign in to this account until a password is set through the link above.

Cheers,
UBAP Team

Need help? Contact us at ubap@clsu.edu.ph.

(c) {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
