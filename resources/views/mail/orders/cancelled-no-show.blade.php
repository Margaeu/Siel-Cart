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

<h1 style="color:#547F12; font-size:20px; margin:0 0 16px;">Order Cancelled</h1>

<p>Hello {{ $order->customer->name }},</p>

<p>Your Siel Cart order <strong>#{{ $order->order_number }}</strong> has been cancelled because it was not collected during the scheduled pickup period.</p>

<p style="color:#547F12; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:2px solid #FEDD04; padding-bottom:6px;">Order Details</p>

<table width="100%" cellpadding="8" cellspacing="0" style="background-color:#f7f9f2; border-radius:6px;">
<tr><td style="font-weight:bold; color:#547F12; width:45%;">Order Number</td><td>#{{ $order->order_number }}</td></tr>
<tr><td style="font-weight:bold; color:#547F12;">Pickup Date</td><td>{{ $order->pickup_date->format('M d, Y') }}</td></tr>
<tr><td style="font-weight:bold; color:#547F12;">Pickup Time</td><td>{{ $order->pickup_slot }}</td></tr>
<tr><td style="font-weight:bold; color:#547F12;">Pickup Location</td><td>{{ ucwords(str_replace('_', ' ', $order->pickup_location)) }}</td></tr>
</table>

<p style="color:#547F12; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:2px solid #FEDD04; padding-bottom:6px;">Order Summary</p>

<table width="100%" cellpadding="0" cellspacing="0">
@foreach ($order->items as $item)
<tr>
<td style="padding:10px 0; border-bottom:1px solid #eeeeee;">
<strong>{{ $item->product_name }}</strong>@if($item->variant_name) — {{ $item->variant_name }}@endif<br>
<span style="color:#666666; font-size:13px;">{{ $item->quantity }} × ₱{{ number_format((float) $item->price, 2) }} = ₱{{ number_format((float) $item->subtotal, 2) }}</span>
</td>
</tr>
@endforeach
<tr>
<td style="padding:12px 0 0; text-align:right; font-weight:bold; color:#547F12;">Order Total: ₱{{ number_format((float) $order->total, 2) }}</td>
</tr>
</table>

<p style="color:#547F12; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:2px solid #FEDD04; padding-bottom:6px;">Payment / Refund</p>

<p><strong>Payment Status:</strong> {{ str($order->payment_status)->headline() }}</p>

<p style="color:#547F12; font-size:16px; font-weight:bold; margin:24px 0 8px; border-bottom:2px solid #FEDD04; padding-bottom:6px;">What happens next?</p>

<p>Your order has been marked as <strong>Cancelled</strong>, and the items have been released from your order.</p>
<p>If you still wish to purchase these items, you may place a new order through Siel Cart, subject to product availability.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
<tr><td align="center">
<a href="{{ route('customer.orders.show', $order) }}" style="display:inline-block; background-color:#FEDD04; color:#547F12; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:14px;">View Order Details</a>
</td></tr>
</table>

<p>If you believe this cancellation was made in error or you have concerns regarding your pickup, please contact the UBAP Office for assistance.</p>

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