<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#000000; padding:20px 0;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; text-align:left;">

  <tr>
    <td style="padding:0 0 16px 0;">
      <p style="margin:0 0 12px 0;">Hello {{ $order->customer->name }},</p>
      <p style="margin:0 0 16px 0;">
        Your order <span style="color:#547F12; font-weight:bold;">#{{ $order->order_number }}</span> has been confirmed and is now being processed.
      </p>
      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="border-radius:4px; background-color:#547F12;">
            <a href="{{ route('customer.orders.show', $order->id) }}" style="display:inline-block; padding:10px 24px; font-size:13px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:4px;">View Order Details</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">ORDER DETAILS</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; line-height:1.6;">
        <tr>
          <td style="width:35%; vertical-align:top; color:#000000;">Order ID:</td>
          <td style="vertical-align:top; color:#547F12; font-weight:bold;">#{{ $order->order_number }}</td>
        </tr>
        <tr>
          <td style="vertical-align:top; color:#000000;">Order Status:</td>
          <td style="vertical-align:top;">Processing</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      @foreach ($order->items as $index => $item)
        <div style="margin-bottom:12px;">
          <p style="margin:0 0 4px 0; font-weight:normal;">
            {{ $index + 1 }}. {{ $item->product_name }}@if($item->variant_name) - {{ $item->variant_name }}@endif
          </p>
          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; line-height:1.5;">
            <tr>
              <td style="width:35%; color:#000000;">Quantity:</td>
              <td>{{ $item->quantity }}</td>
            </tr>
            <tr>
              <td style="color:#000000;">Price:</td>
              <td>₱{{ number_format((float) $item->price, 2) }}</td>
            </tr>
          </table>
        </div>
      @endforeach
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; line-height:1.6;">
        <tr>
          <td style="width:35%; color:#000000;">Subtotal:</td>
          <td>₱{{ number_format((float) $order->total, 2) }}</td>
        </tr>
        <tr>
          <td style="color:#000000; font-weight:normal;">Total Amount:</td>
          <td style="font-weight:bold; color:#000000;">₱{{ number_format((float) $order->total, 2) }}</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:24px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">WHAT'S NEXT</p>
      <p style="margin:0 0 10px 0;">We will notify you once your order is ready for pickup. Please wait for the pickup-ready notification before visiting the pickup location.</p>
      <p style="margin:0 0 4px 0;">Cheers,</p>
      <p style="margin:0;">UBAP Team</p>
    </td>
  </tr>

  <tr>
    <td style="padding-top:12px; font-size:13px; color:#000000;">
      Need help? Contact us <a href="mailto:ubap@clsu.edu.ph" style="color:#547F12; text-decoration:none;">here</a>.
    </td>
  </tr>

</table>
</td>
</tr>
</table>
