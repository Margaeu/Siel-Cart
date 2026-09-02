@php
    $statusClasses = match ($order->status) {
        'completed'        => 'bg-green-100 text-green-800',
        'cancelled'        => 'bg-red-100 text-red-800',
        'ready_for_pickup' => 'bg-blue-100 text-blue-800',
        'processing'       => 'bg-indigo-100 text-indigo-800',
        default            => 'bg-yellow-100 text-yellow-800',
    };

    // Cash on pickup is the only method the shop accepts, but the label is
    // still read off the order so a historic order keeps showing whatever
    // it was actually paid with.
    $paymentMethodLabel = match ($order->payment_method) {
        'cash_on_pickup' => 'Cash on Pickup',
        default          => Str::headline($order->payment_method),
    };
@endphp

<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- header --}}
        <div class="mb-8">
            <nav class="text-sm mb-4">
                <ol class="flex items-center gap-2">
                    <li><a href="{{ route('customer.dashboard') }}" class="text-gray-500 hover:text-[#1E6031]">Account</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="{{ route('customer.orders') }}" class="text-gray-500 hover:text-[#1E6031]">Orders</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-900 font-medium">{{ $order->order_number }}</li>
                </ol>
            </nav>
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-gray-900">Order Details</h1>
                <span class="px-4 py-2 rounded-lg text-sm font-semibold {{ $statusClasses }}">
                    {{ Str::headline($order->status) }}
                </span>
            </div>
        </div>

        @if(session()->has('order_success_title'))
            <div class="bg-[#f2f7f4] border border-[#1E6031] text-[#1E6031] px-5 py-4 rounded-lg mb-6">
                <p class="font-bold">
                    {{ session('order_success_title') }}
                </p>

                <p class="mt-1">
                    {{ session('order_success_message') }}
                </p>
            </div>
        @endif

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
                                $order->payment_status === 'paid'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-yellow-100 text-yellow-800'
                            }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Method</p>
                            <p class="font-semibold text-gray-900">{{ $paymentMethodLabel }}</p>
                        </div>
                    </div>
                </div>

                {{-- Customer Information --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">
                        Customer Information
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Name</p>
                            <p class="font-semibold text-gray-900">
                                {{ $order->customer->name }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-semibold text-gray-900">
                                {{ $order->customer->email }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- order Items --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Order Items</h2>
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            <div class="flex gap-4 pb-4 border-b last:border-b-0 last:pb-0">
                                <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($item->product?->primaryImage)
                                        <img src="{{ $item->product->primaryImage->url }}"
                                             alt="{{ $item->product_name }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-2xl font-semibold">
                                            {{ substr($item->product_name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900">{{ $item->product_name }}</h3>
                                    @if($item->variant_name)
                                        <p class="text-sm text-gray-600">Variation: {{ $item->variant_name }}</p>
                                    @endif
                                    <p class="text-sm text-gray-500">SKU: {{ $item->product_sku }}</p>
                                    <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }} × ₱{{ number_format($item->price, 2) }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-bold text-gray-900">₱{{ number_format($item->subtotal, 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Pickup Information --}}
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Pickup Information</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Pickup Location</p>
                            <p class="font-semibold text-gray-900">{{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pickup Date</p>
                            <p class="font-semibold text-gray-900">
                                @if($order->pickup_date)
                                    {{ $order->pickup_date->format('M d, Y') }}
                                @else
                                    <span class="text-gray-500 font-normal">To be scheduled</span>
                                @endif
                            </p>
                        </div>
                        {{-- The claimant is designated after the order is placed,
                             so both of these are empty on a brand new order. --}}
                        <div>
                            <p class="text-sm text-gray-600">Claimed By</p>
                            <p class="font-semibold text-gray-900">
                                @if($order->pickup_contact_name)
                                    {{ $order->pickup_contact_name }}
                                @else
                                    <span class="text-gray-500 font-normal">To be designated</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Contact Number</p>
                            <p class="font-semibold text-gray-900">
                                @if($order->pickup_contact_phone)
                                    {{ $order->pickup_contact_phone }}
                                @else
                                    <span class="text-gray-500 font-normal">To be designated</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- The claim number is only issued once the order is actually
                         waiting at the office, so most orders will not have one. --}}
                    <div class="mt-4 pt-4 border-t">
                        <p class="text-sm text-gray-600">Claim Number</p>
                        @if($order->claim_number)
                            <p class="font-mono font-bold text-lg text-[#1E6031]">{{ $order->claim_number }}</p>
                            <p class="text-sm text-gray-600 mt-1">
                                Present this at the {{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL }} to collect your order.
                            </p>
                        @elseif($order->status === 'cancelled')
                            <p class="text-gray-500">Not issued — this order was cancelled.</p>
                        @else
                            <p class="text-gray-500">
                                Will be issued once your order is ready to collect.
                            </p>
                        @endif
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
                                        <div class="w-10 h-10 rounded-full bg-[#f2f7f4] text-[#1E6031] flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="font-semibold text-gray-900">{{ Str::headline($history->status) }}</p>
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
                            <span class="text-gray-600">Merchandise Subtotal</span>
                            <span class="font-medium">₱{{ number_format($order->subtotal, 2) }}</span>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold">Order Total</span>
                            <span class="text-2xl font-bold text-[#1E6031]">
                                ₱{{ number_format($order->total, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
