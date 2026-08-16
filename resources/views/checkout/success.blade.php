<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <!-- Success Icon -->
                <div class="mx-auto w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h1 class="text-4xl font-bold text-gray-900 mb-4">Order Placed Successfully!</h1>
                <p class="text-lg text-gray-600 mb-8">
                    Thank you for your order. We've received your order and will process it shortly.
                </p>

                <!-- Order Details Card -->
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-6 mb-8">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-left">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Order Number</p>
                            <p class="font-bold text-gray-900 text-lg">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Order Date</p>
                            <p class="font-semibold text-gray-900">{{ $order->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Amount</p>
                            <p class="font-bold text-gray-900 text-lg">${{ number_format($order->total, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Payment Status</p>
                            <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full {{ 
                                $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' 
                            }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Order Items Preview -->
                <div class="text-left mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h3>
                    <div class="space-y-3">
                        @foreach($order->items->take(3) as $item)
                            <div class="flex items-center gap-4">
                                @if($item->product && $item->product->primaryImage)
                                    <img src="{{ asset('storage/' . $item->product->primaryImage->image_path) }}" 
                                         alt="{{ $item->product_name }}"
                                         class="w-16 h-16 object-cover rounded-lg">
                                @else
                                    <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <span class="text-gray-400 text-xs">No Image</span>
                                    </div>
                                @endif
                                <div class="flex-1 text-left">
                                    <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-sm text-gray-600">{{ $item->variant_name }}</p>
                                    @endif
                                    <p class="text-sm text-gray-600">Qty: {{ $item->quantity }}</p>
                                </div>
                                <p class="font-semibold text-gray-900">${{ number_format($item->subtotal, 2) }}</p>
                            </div>
                        @endforeach
                        @if($order->items->count() > 3)
                            <p class="text-sm text-gray-600 text-center">+ {{ $order->items->count() - 3 }} more items</p>
                        @endif
                    </div>
                </div>

                <!-- Shipping Info -->
                <div class="bg-blue-50 rounded-lg p-4 mb-8 text-left">
                    <h4 class="font-semibold text-gray-900 mb-2">📦 Shipping Address</h4>
                    <p class="text-gray-700">
                        {{ $order->shipping_full_name }}<br>
                        {{ $order->shipping_address_line_1 }}<br>
                        @if($order->shipping_address_line_2)
                            {{ $order->shipping_address_line_2 }}<br>
                        @endif
                        {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postal_code }}<br>
                        {{ $order->shipping_country }}
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('customer.orders.show', $order->id) }}"
                       class="inline-flex items-center justify-center bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View Order Details
                    </a>
                    <a href="{{ route('products.index') }}"
                       class="inline-flex items-center justify-center bg-gray-200 text-gray-900 px-8 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Continue Shopping
                    </a>
                </div>

                <!-- What's Next -->
                <div class="mt-8 pt-8 border-t">
                    <h4 class="font-semibold text-gray-900 mb-4">What happens next?</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-blue-600 rounded-full flex items-center justify-center font-bold">1</div>
                            <div class="text-left">
                                <p class="font-semibold text-gray-900">Order Confirmed</p>
                                <p class="text-gray-600">You'll receive an email confirmation</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-blue-600 rounded-full flex items-center justify-center font-bold">2</div>
                            <div class="text-left">
                                <p class="font-semibold text-gray-900">Order Processing</p>
                                <p class="text-gray-600">We're preparing your items</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-blue-600 rounded-full flex items-center justify-center font-bold">3</div>
                            <div class="text-left">
                                <p class="font-semibold text-gray-900">Shipped & Delivered</p>
                                <p class="text-gray-600">Track your order anytime</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Home -->
            <p class="mt-6 text-center text-gray-600">
                <a href="{{ route('home') }}" class="text-blue-600 hover:text-indigo-700 font-medium">
                    ← Back to Home
                </a>
            </p>
        </div>
    </div>
</body>
</html>