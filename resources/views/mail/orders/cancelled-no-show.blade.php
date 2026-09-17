<x-mail::message>
# Order Cancelled

Hello {{ $order->customer->name }},

Your Siel Cart order **#{{ $order->order_number }}** has been cancelled because it was not collected during the scheduled pickup period.

## Order Details

**Order Number:** #{{ $order->order_number }}  
**Pickup Date:** {{ $order->pickup_date->format('M d, Y') }}  
**Pickup Time:** {{ $order->pickup_slot }}  
**Pickup Location:** {{ ucwords(str_replace('_', ' ', $order->pickup_location)) }}

## Order Summary

@foreach ($order->items as $item)
**{{ $item->product_name }}**@if($item->variant_name) — {{ $item->variant_name }}@endif  
{{ $item->quantity }} × ₱{{ number_format((float) $item->price, 2) }} = ₱{{ number_format((float) $item->subtotal, 2) }}

@endforeach
**Order Total:** ₱{{ number_format((float) $order->total, 2) }}

## Payment / Refund

**Payment Status:** {{ str($order->payment_status)->headline() }}

## What happens next?

Your order has been marked as **Cancelled**, and the items have been released from your order.

If you still wish to purchase these items, you may place a new order through Siel Cart, subject to product availability.

<x-mail::button :url="route('customer.orders.show', $order)">
View Order Details
</x-mail::button>

If you believe this cancellation was made in error or you have concerns regarding your pickup, please contact the UBAP Office for assistance.

Thanks,<br>
UBAP team

**UBAP Office**<br>
Email: ubap@clsu.edu.ph<br>

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
