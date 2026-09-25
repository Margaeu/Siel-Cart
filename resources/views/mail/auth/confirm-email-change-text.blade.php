Hello {{ $notifiable->first_name ?? 'there' }},

You asked to change the email address on your {{ config('app.name') }} account from {{ $currentEmail }} to this address. Confirm it below to finish the change.

CONFIRM YOUR NEW EMAIL ADDRESS
{{ $confirmationUrl }}

This link will expire in {{ $expireMinutes }} minutes. Until you confirm it, your account keeps signing in with {{ $currentEmail }}.

SECURITY NOTE
If you did not request this change, you can ignore this email -- your login email stays {{ $currentEmail }} unless this link is clicked.

Cheers,
UBAP Team

Need help? Contact us at ubap@clsu.edu.ph.

(c) {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
