<x-mail::message>
# Order Collected

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** has been successfully collected.

## Collection Details

**Claimed By:** {{ $order->claimant_name }}<br>
**Contact Number:** {{ $order->claimant_phone }}<br>
**Date Collected:** {{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A') }}

## Returns, Refunds, and Exchanges

For return, refund, or exchange concerns, please contact the UBAP Office directly. Any refund or exchange UBAP makes will be shown on your order details.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

Thank you for choosing SielCart!

We hope you enjoy your CLSU merchandise.

If you have any concerns about your order, please contact the UBAP Office for assistance.

Thanks,<br>
UBAP team

**UBAP Office**<br>
Email: ubap@clsu.edu.ph<br>

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
