<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #1E6031;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .order-details {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .pickup-box {
            background: #f2f7f4;
            border: 1px solid #1E6031;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .item {
            border-bottom: 1px solid #e5e7eb;
            padding: 15px 0;
        }
        .item:last-child {
            border-bottom: none;
        }
        .total {
            background: #1E6031;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            background: #1E6031;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }
        .claim-code {
            font-family: monospace;
            font-size: 22px;
            font-weight: bold;
            color: #1E6031;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">Thank You for Your Order!</h1>
    </div>

    <div class="content">
        <p>Hi {{ $order->customer?->name ?? 'Valued Customer' }},</p>

        <p>
            We've received your order and are getting it ready. We'll let you know
            once it is ready to collect at the {{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL ?? 'Store Pickup Location' }}.
        </p>

        <div class="order-details">
            <h2 style="margin-top: 0;">Order Details</h2>
            <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
            <p><strong>Payment Method:</strong> Cash on Pickup</p>
            <p style="margin-bottom: 0;"><strong>Payment Status:</strong> {{ ucfirst($order->payment_status ?? 'pending') }}</p>
        </div>

        <h3>Order Items</h3>
        @foreach($order->items as $item)
            <div class="item">
                <strong>{{ $item->product_name }}</strong>
                @if($item->variant_name)
                    <br><span style="color: #6b7280;">Variation: {{ $item->variant_name }}</span>
                @endif
                <br>Quantity: {{ $item->quantity }} &times; &#8369;{{ number_format($item->price, 2) }}
                <br><strong>&#8369;{{ number_format($item->subtotal, 2) }}</strong>
            </div>
        @endforeach

        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
            <table width="100%" style="margin-top: 10px;">
                <tr>
                    <td>Merchandise Subtotal:</td>
                    <td align="right">&#8369;{{ number_format($order->subtotal, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="total">
            <table width="100%">
                <tr>
                    <td><strong style="font-size: 18px;">Order Total:</strong></td>
                    <td align="right"><strong style="font-size: 24px;">&#8369;{{ number_format($order->total, 2) }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="pickup-box">
            <h3 style="margin-top: 0;">Pickup Details</h3>
            <p style="margin: 0;"><strong>Location:</strong> {{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL ?? 'Store Pickup Location' }}</p>
            <p style="margin: 6px 0 0;"><strong>Claimed by:</strong> {{ $order->pickup_contact_name ?? 'To be designated' }}</p>
            <p style="margin: 6px 0 0;"><strong>Contact number:</strong> {{ $order->pickup_contact_phone ?? 'To be designated' }}</p>
            <p style="margin: 6px 0 0;">
                <strong>Pickup date:</strong>
                @if($order->pickup_date)
                    {{ $order->pickup_date->format('M d, Y') }}
                @else
                    To be scheduled
                @endif
            </p>

            @if($order->claim_number)
                <p style="margin: 14px 0 0;"><strong>Claim number:</strong></p>
                <p style="margin: 2px 0 0;" class="claim-code">{{ $order->claim_number }}</p>
                <p style="margin: 6px 0 0; font-size: 14px; color: #6b7280;">
                    Present this when you collect your order.
                </p>
            @else
                <p style="margin: 14px 0 0; font-size: 14px; color: #6b7280;">
                    Your claim number will be sent once the order is ready to collect.
                </p>
            @endif
        </div>

        <div style="text-align: center;">
            <a href="{{ route('customer.orders.show', $order->id) }}" class="button">
                View Order Details
            </a>
        </div>
    </div>
</body>
</html>