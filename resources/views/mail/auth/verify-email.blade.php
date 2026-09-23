<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email address</title>
</head>
<body style="margin:0; padding:0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#000000; padding:20px 0;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; text-align:left;">

  <tr>
    <td style="padding:0 0 16px 0;">
      <p style="margin:0 0 12px 0;">Hello {{ $notifiable->first_name ?? 'there' }},</p>
      <p style="margin:0;">
        Thanks for creating a <span style="color:{{ $primaryColor }}; font-weight:bold;">{{ config('app.name') }}</span> account &mdash; The CLSU Campus Store. Please verify your email address to finish setting up your account and start shopping.
      </p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">VERIFY YOUR EMAIL ADDRESS</p>
      <p style="margin:0 0 14px 0;">Click the button below to verify your email address:</p>
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 14px 0;">
        <tr>
          <td align="center" style="background-color:{{ $primaryColor }}; border-radius:4px;">
            <a href="{{ $verificationUrl }}" target="_blank" style="display:inline-block; padding:10px 24px; font-size:13px; font-weight:bold; color:#ffffff; text-decoration:none;">
              Verify Email Address
            </a>
          </td>
        </tr>
      </table>
      <p style="margin:0; font-size:12px; color:#666666;">This verification link will expire in {{ $expireMinutes }} minutes.</p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">HAVING TROUBLE?</p>
      <p style="margin:0 0 8px 0;">If the button above doesn't work, copy and paste this link into your browser:</p>
      <p style="margin:0; word-break:break-all;">
        <a href="{{ $verificationUrl }}" target="_blank" style="color:{{ $primaryColor }};">{{ $verificationUrl }}</a>
      </p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:24px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">SECURITY NOTE</p>
      <p style="margin:0 0 20px 0;">If you did not create an account with {{ config('app.name') }}, no further action is required and you can safely ignore this email.</p>
      <p style="margin:0 0 4px 0;">Cheers,</p>
      <p style="margin:0;">UBAP Team</p>
    </td>
  </tr>

  <tr>
    <td style="padding-top:12px; font-size:13px; color:#000000;">
      Need help? Contact us <a href="mailto:ubap@clsu.edu.ph" style="color:{{ $primaryColor }}; text-decoration:none;">here</a>.
    </td>
  </tr>

  <tr>
    <td style="padding-top:8px; font-size:11px; color:#8a8f8a;">
      &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </td>
  </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
