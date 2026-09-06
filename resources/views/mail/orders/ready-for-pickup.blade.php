<x-mail::message>
# Ready for Pickup!

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** is ready for collection at the UBAP Office.

## Pickup Details

**Claim Number:** {{ $order->claim_number }}<br>
**Pickup Date:** {{ \Carbon\Carbon::parse($order->pickup_date)->format('M d, Y') }}<br>
**Pickup Time:** {{ $order->pickup_slot }}

## Important Reminder

**Note:** Please claim your items within your designated time slot. Unclaimed orders will be cancelled immediately.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

If you have concerns regarding your pickup, please contact the UBAP Office for assistance.

Thanks,<br>
{{ config('app.name') }}

**UBAP Office**<br>
Email: {{ \App\Models\Setting::get('store_email', config('mail.from.address')) }}<br>
Phone: {{ \App\Models\Setting::get('store_phone', 'Contact information unavailable') }}

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
