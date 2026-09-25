<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set your password</title>
</head>
<body style="margin:0; padding:0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#000000; padding:20px 0;">
<tr>
<td align="center" style="padding:0 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; text-align:left;">

  <tr>
    <td style="padding:0 0 16px 0;">
      <p style="margin:0 0 12px 0;">Hello {{ $notifiable->name ?: 'there' }},</p>
      <p style="margin:0;">
        An administrator account has been created for you on the <span style="color:{{ $primaryColor }}; font-weight:bold;">{{ config('app.name') }}</span> admin panel.
      </p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">YOUR ACCOUNT</p>
      <p style="margin:0 0 4px 0;">Sign-in email: <strong>{{ $notifiable->email }}</strong></p>
      <p style="margin:0;">No password has been set for you. You choose your own below.</p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">SET YOUR PASSWORD</p>
      <p style="margin:0 0 14px 0;">Click the button below to choose and confirm your password:</p>
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 14px 0;">
        <tr>
          <td align="center" style="background-color:{{ $primaryColor }}; border-radius:4px;">
            <a href="{{ $setupUrl }}" target="_blank" style="display:inline-block; padding:14px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">
              Set your password
            </a>
          </td>
        </tr>
      </table>
      <p style="margin:0 0 8px 0; font-size:12px; color:#666666;">This link will expire in {{ $expireMinutes }} minute{{ (int) $expireMinutes === 1 ? '' : 's' }} and can only be used once. If it expires, ask a super admin to resend your invitation.</p>
      <p style="margin:0;">Once your password is set, sign in at <a href="{{ $loginUrl }}" target="_blank" style="color:{{ $primaryColor }};">{{ $loginUrl }}</a>.</p>
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
        <a href="{{ $setupUrl }}" target="_blank" style="color:{{ $primaryColor }};">{{ $setupUrl }}</a>
      </p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:24px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">SECURITY NOTE</p>
      <p style="margin:0 0 20px 0;">If you were not expecting this invitation, you can ignore this email. No one can sign in to this account until a password is set through the link above.</p>
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
