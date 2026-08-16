<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- header --}}
        <div class="mb-8">
            <nav class="text-sm mb-4">
                <ol class="flex items-center gap-2">
                    <li><a href="{{ route('customer.dashboard') }}" class="text-gray-500 hover:text-blue-600">Account</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="{{ route('customer.orders') }}" class="text-gray-500 hover:text-blue-600">Orders</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-900 font-medium">{{ $order->order_number }}</li>
                </ol>
            </nav>
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Order Details</h1>
                <span class="px-4 py-2 rounded-lg text-sm font-semibold {{ 
                    $order->status === 'delivered' ? 'bg-green-100 text-green-800' : 
                    ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : 
                    ($order->status === 'shipped' ? 'bg-blue-100 text-blue-800' :
                    'bg-yellow-100 text-yellow-800')) 
                }}">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
        </div>
        {{-- content/ grid --}}

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                {{-- order info --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order Information</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Order Number</p>
                            <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Order Date</p>
                            <p class="font-semibold text-gray-900">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Status</p>
                            <span class="inline-block px-2 py-1 text-sm rounded {{ 
                                $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                'bg-yellow-100 text-yellow-800' 
                            }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Method</p>
                            <p class="font-semibold text-gray-900">
                                {{ $order->payment_method === 'stripe' ? 'Credit/Debit Card' : 'Cash on Delivery' }}
                            </p>
                        </div>
                        @if($order->tracking_number)
                            <div class="col-span-2">
                                <p class="text-sm text-gray-600">Tracking Number</p>
                                <p class="font-semibold text-gray-900 font-mono">{{ $order->tracking_number }}</p>
                            </div>
                        @endif
                    </div>
                </div>
                {{-- order Items --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order Items</h2>
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            <div class="flex gap-4 pb-4 border-b last:border-b-0">
                                <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($item->product && $item->product->primaryImage)
                                        <img src="{{ asset('storage/' . $item->product->primaryImage->image_path) }}" 
                                             alt="{{ $item->product_name }}"
                                             class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900">{{ $item->product_name }}</h3>
                                    @if($item->variant_name)
                                        <p class="text-sm text-gray-600">{{ $item->variant_name }}</p>
                                    @endif
                                    <p class="text-sm text-gray-600">SKU: {{ $item->product_sku }}</p>
                                    <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }} × ${{ number_format($item->price, 2) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-900">${{ number_format($item->subtotal, 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                {{-- Shipping Address --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
                    <div class="text-gray-700">
                        <p class="font-semibold">{{ $order->shipping_full_name }}</p>
                        <p>{{ $order->shipping_phone }}</p>
                        <p class="mt-2">{{ $order->shipping_address_line_1 }}</p>
                        @if($order->shipping_address_line_2)
                            <p>{{ $order->shipping_address_line_2 }}</p>
                        @endif
                        <p>{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postal_code }}</p>
                        <p>{{ $order->shipping_country }}</p>
                    </div>
                </div>
                {{-- Order History --}}
                @if($order->statusHistories->count() > 0)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order History</h2>
                    <div class="space-y-4">
                        @foreach($order->statusHistories as $history)
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-blue-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-gray-900">{{ ucfirst($history->status) }}</p>
                                        <p class="text-sm text-gray-500">{{ $history->created_at->format('M d, Y h:i A') }}</p>
                                    </div>
                                    @if($history->notes)
                                        <p class="text-sm text-gray-600 mt-1">{{ $history->notes }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            </div>
            {{-- Order Summary --}}
            <div>
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">${{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        @if($order->discount_amount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Discount</span>
                                <span class="font-medium">-${{ number_format($order->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium">
                                @if($order->shipping_cost > 0)
                                    ${{ number_format($order->shipping_cost, 2) }}
                                @else
                                    <span class="text-green-600">FREE</span>
                                @endif
                            </span>
                        </div>
                        @if($order->tax_amount > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax</span>
                                <span class="font-medium">${{ number_format($order->tax_amount, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="border-t pt-4 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold">Total</span>
                            <span class="text-2xl font-bold text-blue-600">
                                ${{ number_format($order->total, 2) }}
                            </span>
                        </div>
                    </div>

                    @if($order->customer_notes)
                        <div class="border-t pt-4">
                            <p class="text-sm font-medium text-gray-900 mb-2">Order Notes</p>
                            <p class="text-sm text-gray-600">{{ $order->customer_notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
