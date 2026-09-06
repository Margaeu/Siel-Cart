<x-mail::message>
# Order Collected

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** has been successfully collected.

## Collection Details

**Claimed By:** {{ $order->claimant_name }}<br>
**Contact Number:** {{ $order->claimant_name }}<br>
**Date Collected:** {{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A') }}

## Returns and Refunds

If you experience any issues, eligible items can be submitted for return/refund within **3 days** of collection.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

If you have concerns regarding your collected order, please contact the UBAP Office for assistance.

Thanks,<br>
{{ config('app.name') }}

**UBAP Office**<br>
Email: {{ \App\Models\Setting::get('store_email', config('mail.from.address')) }}<br>
Phone: {{ \App\Models\Setting::get('store_phone', 'Contact information unavailable') }}

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
