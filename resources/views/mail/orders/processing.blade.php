<x-mail::message>
# Order Confirmed!

Hello {{ $order->customer->name }},

We have confirmed your order **#{{ $order->order_number }}**! Please wait for our admin team to process your order and email you again with your claim number and pickup slot.

*Estimated Fulfillment Time:* It may take within **3–5 business days** to fulfill your order.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>