<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; font-family:Arial, Helvetica, sans-serif;">

<tr>
<td style="padding:30px; color:#374151; font-size:15px; line-height:1.6;">

<h1 style="color:#111827; font-size:20px; margin:0 0 16px;">Order Collected</h1>

<p>Hello {{ $order->customer->name }},</p>

<p>Your order <strong style="color:547F12">#{{ $order->order_number }}</strong> has been successfully collected.</p>

<p style="color:#111827; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:1px solid #e5e7eb; padding-bottom:6px;">Collection Details</p>

<table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:6px;">
<tr>
<td style="font-weight:bold; color:#374151; width:45%;">Claimed By</td>
<td style="color:#4b5563;">{{ $order->claimant_name }}</td>
</tr>
<tr>
<td style="font-weight:bold; color:#374151;">Contact Number</td>
<td style="color:#4b5563;">{{ $order->claimant_phone }}</td>
</tr>
<tr>
<td style="font-weight:bold; color:#374151;">Date Collected</td>
<td style="color:#4b5563;">{{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A') }}</td>
</tr>
</table>

<p style="color:#111827; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:1px solid #e5e7eb; padding-bottom:6px;">Returns, Refunds, and Exchanges</p>

<p style="color:#4b5563;">For return, refund, or exchange concerns, you may contact UBAP via email or visit the UBAP Office directly. Any refund or exchange processed by UBAP will be reflected in your order details.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
<tr><td align="center">
<a href="{{ route('customer.orders') }}" style="display:inline-block; background-color:#111827; color:#ffffff; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:14px;">View Order Details</a>
</td></tr>
</table>

<p style="color:#4b5563;">Thank you for choosing Siel Cart!</p>
<p style="color:#4b5563;">We hope you enjoy your CLSU merchandise.</p>
<p style="color:#4b5563;">If you have any concerns about your order, please contact the UBAP Office for assistance.</p>

<p style="margin-top:24px; color:#4b5563;">Thanks,<br><strong style="color:#111827;">UBAP team</strong></p>

</td>
</tr>

<tr>
<td style="border-top:1px solid #e5e7eb; padding:20px 30px; text-align:center; font-size:12px; color:#6b7280;">
<strong style="color:#374151;">UBAP Office</strong><br>
Email: <a href="mailto:ubap@clsu.edu.ph" style="color:#4b5563; text-decoration:underline;">ubap@clsu.edu.ph</a><br><br>
This is an automated email. Please do not reply directly to this message.
</td>
</tr>

</table>
</td></tr>
</table>