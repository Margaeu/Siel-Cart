<x-mail::message>
# Ready for Pickup!

Hello {{ $order->customer->name }},

Your order **#{{ $order->order_number }}** is ready for collection at the UBAP Office.

## Pickup Details

**Claim Number:** {{ $order->claim_number }}<br>
**Pickup Date:** {{ \Carbon\Carbon::parse($order->pickup_date)->format('M d, Y') }}<br>
**Pickup Time:** {{ $order->pickup_slot }}

## Important Reminder

**Note:** 
Please have your Claim number ready when collecting your order.
If someone else will collect the order on your behalf, please make sure they have the required authorization and order information.
Please claim your items within your designated time slot. Unclaimed orders will be cancelled immediately.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>


Thanks,<br>
UBAP team

**UBAP Office**<br>
Email: ubap@clsu.edu.ph<br>

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
