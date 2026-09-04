<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
            <p class="text-gray-600 mt-1">
                Everything in your cart will be ordered. To change what you are buying,
                <a href="{{ route('cart.index') }}" class="text-[#1E6031] font-medium hover:underline">go back to your cart</a>.
            </p>
        </div>

        @if (session()->has('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-6">

            {{-- ── Section 1: Customer Information ───────────────────────── --}}
            <section class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-[#1E6031] text-white font-semibold text-sm">1</span>
                    <h2 class="text-xl font-bold text-gray-900">Customer Information</h2>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-gray-600">Name</p>
                        <p class="font-semibold text-gray-900">{{ $customer->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-semibold text-gray-900">{{ $customer->email }}</p>
                    </div>
                </div>

                <p class="text-sm text-gray-500 mt-4">
                    This comes from your account. Update it in your
                    <a href="{{ route('customer.profile') }}" class="text-[#1E6031] hover:underline">profile</a>.
                </p>
            </section>

            {{-- ── Section 2: Products Ordered ───────────────────────────── --}}
            <section class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-[#1E6031] text-white font-semibold text-sm">2</span>
                    <h2 class="text-xl font-bold text-gray-900">Products Ordered</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($cart as $item)
                        <div wire:key="checkout-item-{{ $item['cart_item_id'] }}" class="flex gap-4 py-4 first:pt-0 last:pb-0">
                            <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                @if($item['image'])
                                    <img src="{{ $item['image'] }}"
                                         alt="{{ $item['name'] }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-2xl font-semibold">
                                        {{ substr($item['name'], 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900">{{ $item['name'] }}</h3>
                                @if($item['variant_name'])
                                    <p class="text-sm text-gray-600 mt-0.5">Variation: {{ $item['variant_name'] }}</p>
                                @endif
                                @if($item['sku'])
                                    <p class="text-sm text-gray-500 mt-0.5">SKU: {{ $item['sku'] }}</p>
                                @endif
                                <p class="text-sm text-gray-600 mt-1">
                                    ₱{{ number_format($item['price'], 2) }} × {{ $item['quantity'] }}
                                </p>
                            </div>

                            <div class="text-right flex-shrink-0">
                                <p class="font-bold text-gray-900">
                                    ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ── Section 3: Fulfilment, Payment & Placement ────────────── --}}
            <section class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-[#1E6031] text-white font-semibold text-sm">3</span>
                    <h2 class="text-xl font-bold text-gray-900">Fulfilment &amp; Payment</h2>
                </div>

                {{-- Fulfilment method: fixed, nothing to choose --}}
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Fulfilment Method</h3>
                    <div class="border-2 border-[#1E6031] bg-[#f2f7f4] rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-[#1E6031] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <div>
                                <p class="font-semibold text-gray-900">Pickup</p>
                                <p class="text-sm text-gray-600">Collect your order at the {{ $pickupLocation }} once it is ready.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Payment method: fixed, nothing to choose --}}
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Payment Method</h3>
                    <div class="border-2 border-[#1E6031] bg-[#f2f7f4] rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-[#1E6031] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $paymentMethod }}</p>
                                <p class="text-sm text-gray-600">Pay in cash when you collect your order.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Order total --}}
                <div class="border-t pt-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Order Total</h3>

                    <div class="space-y-3">
                        <div class="flex justify-between text-gray-600">
                            <span>Merchandise Subtotal ({{ count($cart) }} {{ Str::plural('item', count($cart)) }})</span>
                            <span class="font-medium text-gray-900">₱{{ number_format($subtotal, 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center border-t pt-3">
                            <span class="text-lg font-semibold text-gray-900">Order Total</span>
                            <span class="text-2xl font-bold text-[#1E6031]">₱{{ number_format($total, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Placement --}}
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-4 mt-6 pt-6 border-t">
                    <a href="{{ route('cart.index') }}"
                       class="text-gray-600 hover:text-gray-900 font-medium text-center sm:text-left">
                        ← Back to Cart
                    </a>

                    <button type="button"
                            wire:click="placeOrder"
                            wire:target="placeOrder"
                            wire:loading.attr="disabled"
                            @disabled($placingOrder)
                            class="bg-[#1E6031] text-white px-8 py-3 rounded-lg hover:bg-[#164824] transition font-semibold disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="placeOrder">Place Order</span>
                        <span wire:loading wire:target="placeOrder">Placing order…</span>
                    </button>
                </div>
            </section>
        </div>
    </div>
</div>
