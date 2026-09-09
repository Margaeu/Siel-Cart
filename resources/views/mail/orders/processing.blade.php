<x-mail::message>
# Order Confirmed!

Hello {{ $order->customer->name }},

We have confirmed your order **#{{ $order->order_number }}**!

## What happens next?

Please wait for our admin team to process your order and email you again with your claim number and pickup slot.

**Estimated Fulfillment Time:** It may take within **2-3 business days** to fulfill your order.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

Thanks,<br>
UBAP team

**UBAP Office**<br>
Email: ubap@clsu.edu.ph<br>

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
