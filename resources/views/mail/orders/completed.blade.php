<x-mail::message>
# Order Collected

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** has been successfully collected.

**Collection Details:**
- **Claimed By:** {{ $order->pickup_contact_name }}
- **Contact Number:** {{ $order->pickup_contact_phone }}
- **Date Collected:** {{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A') }}

If you experience any issues, eligible items can be submitted for return/refund within **15 days** of collection.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>