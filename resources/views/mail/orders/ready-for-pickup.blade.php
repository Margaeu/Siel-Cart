<x-mail::message>
# Ready for Pickup!

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** is ready for collection at the UBAP Office.

* **Claim Number:** {{ $order->claim_number }}
* **Pickup Date:** {{ \Carbon\Carbon::parse($order->pickup_date)->format('M d, Y') }}
* **Time Slot:** {{ $order->pickup_slot }}

*Note:* Please claim your items within your designated time slot. Unclaimed orders will be cancelled manually.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>