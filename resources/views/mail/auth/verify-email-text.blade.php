Hello {{ $notifiable->first_name ?? 'there' }},

Thanks for creating a {{ config('app.name') }} account -- The CLSU Campus Store. Please verify your email address to finish setting up your account and start shopping.

VERIFY YOUR EMAIL ADDRESS
{{ $verificationUrl }}

This verification link will expire in {{ $expireMinutes }} minutes.

SECURITY NOTE
If you did not create an account with {{ config('app.name') }}, no further action is required and you can safely ignore this email.

Cheers,
UBAP Team

Need help? Contact us at ubap@clsu.edu.ph.

(c) {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
