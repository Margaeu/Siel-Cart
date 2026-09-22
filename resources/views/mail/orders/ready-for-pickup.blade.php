<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:20px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; font-family:Arial, Helvetica, sans-serif;">

<tr>
<td style="background-color:#547F12; padding:24px 30px;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="color:#FEDD04; font-size:22px; font-weight:bold;">Siel Cart</td>
<td style="color:#ffffff; font-size:13px; text-align:right;">UBAP Office</td>
</tr></table>
</td>
</tr>

<tr>
<td style="padding:30px; color:#333333; font-size:15px; line-height:1.6;">

<h1 style="color:#547F12; font-size:20px; margin:0 0 16px;">Ready for Pickup!</h1>

<p>Hello {{ $order->customer->name }},</p>

<p>Your order <strong>#{{ $order->order_number }}</strong> is ready for collection at the UBAP Office.</p>

<p style="color:#547F12; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:2px solid #FEDD04; padding-bottom:6px;">Pickup Details</p>

<table width="100%" cellpadding="8" cellspacing="0" style="background-color:#f7f9f2; border-radius:6px;">
<tr>
<td style="font-weight:bold; color:#547F12; width:45%;">Claim Number</td>
<td>{{ $order->claim_number }}</td>
</tr>
<tr>
<td style="font-weight:bold; color:#547F12;">Pickup Date</td>
<td>{{ \Carbon\Carbon::parse($order->pickup_date)->format('M d, Y') }}</td>
</tr>
<tr>
<td style="font-weight:bold; color:#547F12;">Pickup Time</td>
<td>{{ $order->pickup_slot }}</td>
</tr>
</table>

<div style="background-color:#fff9d6; border-left:4px solid #FEDD04; padding:14px 16px; margin:20px 0; font-size:14px;">
<strong style="color:#547F12;">Note:</strong><br>
Please have your Claim number ready when collecting your order.<br>
If someone else will collect the order on your behalf, please make sure they have the required authorization and order information.<br>
Please claim your items within your designated time slot. Unclaimed orders will be cancelled immediately.
</div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
<tr><td align="center">
<a href="{{ route('customer.orders') }}" style="display:inline-block; background-color:#FEDD04; color:#547F12; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:14px;">View Order Details</a>
</td></tr>
</table>

<p style="margin-top:24px;">Thanks,<br>UBAP team</p>

</td>
</tr>

<tr>
<td style="background-color:#f4f4f4; padding:20px 30px; text-align:center; font-size:12px; color:#777777;">
<strong style="color:#547F12;">UBAP Office</strong><br>
Email: ubap@clsu.edu.ph<br><br>
This is an automated email. Please do not reply directly to this message.
</td>
</tr>

</table>
</td></tr>
</table>