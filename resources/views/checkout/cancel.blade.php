<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <!-- Warning Icon -->
                <div class="mx-auto w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h1 class="text-4xl font-bold text-gray-900 mb-4">Payment Cancelled</h1>
                <p class="text-lg text-gray-600 mb-8">
                    Your payment was cancelled. Your order is still pending and you can complete the payment anytime.
                </p>

                <!-- Order Info -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-left">
                            <p class="text-sm text-gray-600">Order Number</p>
                            <p class="font-bold text-gray-900">{{ $order->order_number }}</p>
                        </div>
                        <div class="text-left">
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="font-bold text-gray-900">${{ number_format($order->total, 2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('checkout') }}"
                       class="inline-flex items-center justify-center bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        Try Payment Again
                    </a>
                    <a href="{{ route('customer.orders.show', $order->id) }}"
                       class="inline-flex items-center justify-center bg-gray-200 text-gray-900 px-8 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                        View Order Details
                    </a>
                </div>

                <!-- Help Text -->
                <div class="mt-8 pt-8 border-t">
                    <p class="text-gray-600 mb-4">Need help with your order?</p>
                    <p class="text-sm text-gray-600">
                        Contact our support team at 
                        <a href="mailto:{{ config('mail.from.address') }}" class="text-blue-600 hover:text-indigo-700 font-medium">
                            {{ config('mail.from.address') }}
                        </a>
                    </p>
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