<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#000000; padding:20px 0;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; text-align:left;">

  <tr>
    <td style="padding:0 0 16px 0;">
      <p style="margin:0 0 12px 0;">Hello {{ $order->customer->name }},</p>
      <p style="margin:0 0 16px 0;">
        Your order <span style="color:#547F12; font-weight:bold;">#{{ $order->order_number }}</span> has been successfully collected.
      </p>
      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="border-radius:4px; background-color:#547F12;">
            <a href="{{ route('customer.orders.show', $order->id) }}" style="display:inline-block; padding:14px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:4px;">View Order Details</a>
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
          <td style="white-space:nowrap; padding-right:12px; vertical-align:top; color:#000000;">Order ID:</td>
          <td style="width:100%; vertical-align:top; color:#547F12; font-weight:bold;">#{{ $order->order_number }}</td>
        </tr>
        <tr>
          <td style="white-space:nowrap; padding-right:12px; vertical-align:top; color:#000000;">Order Status:</td>
          <td style="width:100%; vertical-align:top;">Collected</td>
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
              <td style="white-space:nowrap; padding-right:12px; color:#000000;">Quantity:</td>
              <td style="width:100%;">{{ $item->quantity }}</td>
            </tr>
            <tr>
              <td style="white-space:nowrap; padding-right:12px; color:#000000;">Price:</td>
              <td style="width:100%;">₱{{ number_format((float) $item->price, 2) }}</td>
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
          <td style="white-space:nowrap; padding-right:12px; color:#000000;">Subtotal:</td>
          <td style="width:100%;">₱{{ number_format((float) $order->total, 2) }}</td>
        </tr>
        <tr>
          <td style="white-space:nowrap; padding-right:12px; color:#000000; font-weight:normal;">Total Amount:</td>
          <td style="width:100%; font-weight:bold; color:#000000;">₱{{ number_format((float) $order->total, 2) }}</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">COLLECTION DETAILS</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; line-height:1.6;">
        <tr>
          <td style="white-space:nowrap; padding-right:12px; vertical-align:top; color:#000000;">Claimed By:</td>
          <td style="width:100%; vertical-align:top;">{{ $order->claimant_name }}</td>
        </tr>
        {{-- Only shown when a separate claimant number was recorded. Blank
             means the customer collected the order themselves, and an empty
             "Contact Number:" row reads like missing data. --}}
        @if($order->claimant_phone)
        <tr>
          <td style="white-space:nowrap; padding-right:12px; vertical-align:top; color:#000000;">Contact Number:</td>
          <td style="width:100%; vertical-align:top;">{{ $order->claimant_phone }}</td>
        </tr>
        @endif
        <tr>
          <td style="white-space:nowrap; padding-right:12px; vertical-align:top; color:#000000;">Date Collected:</td>
          <td style="width:100%; vertical-align:top;">{{ $order->completed_at ? $order->completed_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A') }}</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:16px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">RETURNS, REFUNDS, AND EXCHANGES</p>
      <p style="margin:0;">For return, refund, or exchange concerns, you may contact UBAP via email or visit the UBAP Office directly. Any refund or exchange processed by UBAP will be reflected in your order details.</p>
    </td>
  </tr>

  <tr>
    <td style="border-top:1px solid #e5e5e5; padding-top:16px;"></td>
  </tr>

  <tr>
    <td style="padding-bottom:24px;">
      <p style="margin:0 0 12px 0; font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">WHAT'S NEXT</p>
      <p style="margin:0 0 10px 0;">Thank you for choosing Siel Cart! We hope you enjoy your CLSU merchandise.</p>
      <p style="margin:0 0 20px 0;">If you have any concerns about your order, please contact the UBAP Office for assistance.</p>
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
