<x-mail::message>
# Order Confirmed!

Hello {{ $order->customer->name }},

We have confirmed your order **#{{ $order->order_number }}**!

## What happens next?

Please wait for our admin team to process your order and email you again with your claim number and pickup slot.

**Estimated Fulfillment Time:** It may take within **3–5 business days** to fulfill your order.

<x-mail::button :url="route('customer.orders')">
View Order Details
</x-mail::button>

If you have concerns regarding your order, please contact the UBAP Office for assistance.

Thanks,<br>
{{ config('app.name') }}

**UBAP Office**<br>
Email: {{ \App\Models\Setting::get('store_email', config('mail.from.address')) }}<br>
Phone: {{ \App\Models\Setting::get('store_phone', 'Contact information unavailable') }}

This is an automated email. Please do not reply directly to this message.
</x-mail::message>
