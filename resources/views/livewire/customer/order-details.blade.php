<div>
    @php
        $statusClasses = match ($order->status) {
            'completed'        => 'bg-green-100 text-green-800',
            'return_completed' => 'bg-green-100 text-green-800',
            'cancelled'        => 'bg-red-100 text-red-800',
            'ready_for_pickup' => 'bg-blue-100 text-blue-800',
            'processing'       => 'bg-indigo-100 text-indigo-800',
            'return_requested' => 'bg-yellow-100 text-yellow-800',
            default            => 'bg-yellow-100 text-yellow-800',
        };

        $paymentMethodLabel = match ($order->payment_method) {
            'cash_on_pickup' => 'Cash on Pickup',
            default          => Str::headline($order->payment_method),
        };

        $paymentStatus = strtolower($order->status) === 'cancelled' 
            ? 'cancelled' 
            : $order->payment_status;
    @endphp

    <div class="bg-gray-50 py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
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
                    
                    <div class="flex items-center gap-3">
                        {{-- Cancellation Modal for Pending Orders --}}
                        @livewire('cancel-order-modal', ['order' => $order])

                        {{-- 1. Active Styled Livewire Button when order is Completed --}}
                        @if(strtolower($order->status) === 'completed')
                            <button type="button" 
                                    wire:click="requestReturn" 
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-[#1E6031] rounded-lg shadow-sm hover:bg-[#154522] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1E6031] transition-all cursor-pointer disabled:opacity-50">
                                <svg wire:loading.remove wire:target="requestReturn" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/>
                                </svg>
                                <span wire:loading wire:target="requestReturn" class="mr-2">
                                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </span>
                                Request Return / Refund
                            </button>

                            <span class="px-4 py-2 rounded-lg text-sm font-semibold {{ $statusClasses }}">
                                Completed
                            </span>

                        {{-- 2. Single Badge when Return is already requested or completed --}}
                        @elseif(in_array(strtolower($order->status), ['return_requested', 'return requested', 'return_completed']))
                            <span class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-amber-100 text-amber-800 border border-amber-300">
                                {{ Str::headline($order->status) }}
                            </span>

                        {{-- 3. Default Status Badge for all other states --}}
                        @else
                            <span class="px-4 py-2 rounded-lg text-sm font-semibold {{ $statusClasses }}">
                                {{ Str::headline($order->status) }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            @if(session()->has('order_success_title'))
                <div class="bg-[#f2f7f4] border border-[#1E6031] text-[#1E6031] px-5 py-4 rounded-lg mb-6">
                    <p class="font-bold">{{ session('order_success_title') }}</p>
                    <p class="mt-1">{{ session('order_success_message') }}</p>
                </div>
            @endif

            {{-- Grid Content --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    {{-- Order Information --}}
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
                                <span class="inline-block px-2 py-1 text-sm rounded font-medium
                                    @if($paymentStatus === 'paid')
                                        bg-green-100 text-green-800
                                    @elseif($paymentStatus === 'cancelled')
                                        bg-red-100 text-red-800
                                    @else
                                        bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ ucfirst($paymentStatus) }}
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
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Customer Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Name</p>
                                <p class="font-semibold text-gray-900">{{ $order->customer->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-semibold text-gray-900">{{ $order->customer->email }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Order Items --}}
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Items</h2>
                        <div class="space-y-4">
                            @foreach($order->items as $item)
                                <div class="flex gap-4 pb-4 border-b last:border-b-0 last:pb-0">
                                    <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                        @if($item->display_image_url)
                                            <img
                                                src="{{ $item->display_image_url }}"
                                                alt="{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }}"
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
                                        @if($item->product_sku)
                                            <p class="text-sm text-gray-500">SKU: {{ $item->product_sku }}</p>
                                        @endif
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
                                <p class="text-gray-500">Will be issued once your order is ready to collect.</p>
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

                            {{-- 1. Display Completed / Collected At Date --}}
                            @if(strtolower($order->status) === 'completed')
                                <div class="flex justify-between items-center text-sm border-t pt-3">
                                    <span class="text-gray-600">Collected At</span>
                                    <span class="font-semibold text-emerald-700">
                                        {{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : $order->updated_at->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                            @endif

                            {{-- 2. Display Cancelled Date --}}
                            @if(strtolower($order->status) === 'cancelled')
                                <div class="flex justify-between items-center text-sm border-t pt-3">
                                    <span class="text-gray-600">Cancelled On</span>
                                    <span class="font-semibold text-red-600">
                                        {{ $order->cancelled_at ? $order->cancelled_at->format('M d, Y h:i A') : $order->updated_at->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                            @endif

                            {{-- 3. Display Return Completed Date --}}
                            @if(strtolower($order->status) === 'return_completed')
                                <div class="flex justify-between items-center text-sm border-t pt-3">
                                    <span class="text-gray-600">Refunded On</span>
                                    <span class="font-semibold text-amber-700">
                                        {{ $order->updated_at->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                            @endif
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
</div>