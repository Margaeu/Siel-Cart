@php
    // Shared control chrome, matching the cart, catalog, and account pages:
    // every interactive target clears the 44px minimum and shows the same
    // primary-coloured focus ring.
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2';
    $card = 'bg-white rounded-2xl shadow-sm border border-gray-100';
    $stepBadge = 'flex size-7 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary)] text-xs font-bold text-white';

    $itemCount = collect($cart)->sum('quantity');
@endphp

<div class="bg-gray-50 min-h-screen">
    <div class="mx-auto max-w-7xl px-4 pt-6 pb-12 sm:px-6 sm:pt-8 lg:px-8">

        {{-- Breadcrumb --}}
        <nav class="mb-3 text-xs text-gray-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">Home</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('cart.index') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">Shopping Cart</a></li>
                <li aria-hidden="true">/</li>
                <li class="font-medium text-gray-700" aria-current="page">Checkout</li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="mb-8">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-2xl font-extrabold tracking-[-0.02em] text-gray-900 sm:text-3xl">
                    Checkout
                </h1>
                @if($itemCount > 0)
                    <span class="text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-gray-500">
                        {{ $itemCount }} {{ Str::plural('item', $itemCount) }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Everything in your cart will be ordered. To change what you are buying,
                <a href="{{ route('cart.index') }}" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">go back to your cart</a>.
            </p>

            {{-- Same gold-into-hairline rule as the catalog and cart headers. --}}
            <div class="mt-4 flex items-center" aria-hidden="true">
                <span class="h-[3px] w-14 rounded-full bg-[var(--color-secondary)]"></span>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>
        </div>

        @if (session()->has('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-6">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <div class="lg:col-span-8 space-y-6">

                {{-- ── Section 1: Customer Information ───────────────────────── --}}
                <section class="{{ $card }} p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="{{ $stepBadge }}">1</span>
                        <h2 class="text-base font-bold text-gray-900">Customer Information</h2>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
                            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-gray-400">Name</p>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $customer->name }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 min-w-0">
                            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-gray-400">Email</p>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5 truncate">{{ $customer->email }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mt-4">
                        This comes from your account. Update it in your
                        <a href="{{ route('customer.profile') }}" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">profile</a>.
                    </p>
                </section>

                {{-- ── Section 2: Products Ordered ───────────────────────────── --}}
                <section class="{{ $card }} p-6">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-3">
                            <span class="{{ $stepBadge }}">2</span>
                            <h2 class="text-base font-bold text-gray-900">Products Ordered</h2>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @foreach($cart as $item)
                            <div wire:key="checkout-item-{{ $item['cart_item_id'] }}" class="flex gap-4 py-5 last:pb-0">
                                <div class="size-20 shrink-0 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                                    @if($item['image'])
                                        <img src="{{ $item['image'] }}"
                                             alt="{{ $item['name'] }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-xl font-semibold">
                                            {{ substr($item['name'], 0, 1) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 leading-snug">{{ $item['name'] }}</h3>
                                    @if($item['variant_name'])
                                        <p class="text-xs text-gray-500 mt-0.5">Variation: {{ $item['variant_name'] }}</p>
                                    @endif
                                    @if($item['sku'])
                                        <p class="text-[0.6875rem] text-gray-400 mt-0.5">SKU: {{ $item['sku'] }}</p>
                                    @endif
                                    <p class="text-sm text-gray-500 mt-1.5">
                                        <span class="font-semibold text-[var(--color-primary)]">₱{{ number_format($item['price'], 2) }}</span>
                                        × {{ $item['quantity'] }}
                                    </p>
                                </div>

                                <div class="text-right shrink-0">
                                    <p class="font-bold text-gray-900">
                                        ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

            </div>

            {{-- ── Section 3: Fulfilment, Payment & Placement ────────────── --}}
            <aside class="lg:col-span-4 lg:sticky lg:top-24">
                <section class="{{ $card }} p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="{{ $stepBadge }}">3</span>
                        <h2 class="text-base font-bold text-gray-900">Pickup &amp; Payment</h2>
                    </div>

                    {{--
                        Fulfilment and payment are fixed server-side constants
                        on CheckoutPage, so these are statements, not choices.
                    --}}
                    <div class="space-y-3">
                        {{-- Fulfilment method --}}
                        <div class="flex items-start gap-3 rounded-xl bg-gray-50 border border-gray-100 p-3.5">
                            <div class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-[var(--color-primary)] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">Pickup</p>
                                <p class="text-xs text-gray-500 leading-relaxed">Collect your order at the {{ $pickupLocation }} once it is ready.</p>
                            </div>
                        </div>

                        {{-- Payment method --}}
                        <div class="flex items-start gap-3 rounded-xl bg-gray-50 border border-gray-100 p-3.5">
                            <div class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-[var(--color-primary)] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $paymentMethod }}</p>
                                <p class="text-xs text-gray-500 leading-relaxed">Pay in cash when you collect your order.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Order total --}}
                    <div class="border-t border-gray-100 mt-5 pt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Merchandise Subtotal ({{ count($cart) }} {{ Str::plural('item', count($cart)) }})</span>
                            <span class="font-medium text-gray-900">₱{{ number_format($subtotal, 2) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 mt-5 pt-5 mb-5">
                        <div class="flex justify-between items-baseline">
                            <span class="font-semibold text-gray-900">Order Total</span>
                            <span class="text-2xl font-extrabold tracking-[-0.02em] text-[var(--color-primary)]">₱{{ number_format($total, 2) }}</span>
                        </div>
                    </div>

                    {{-- Placement --}}
                    <button type="button"
                            wire:click="placeOrder"
                            wire:target="placeOrder"
                            wire:loading.attr="disabled"
                            @disabled($placingOrder)
                            class="flex min-h-11 w-full items-center justify-center rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60 disabled:cursor-not-allowed {{ $focusRing }}">
                        <span wire:loading.remove wire:target="placeOrder">Place Order</span>
                        <span wire:loading wire:target="placeOrder">Placing order…</span>
                    </button>

                    <a href="{{ route('cart.index') }}"
                       class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-6 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 {{ $focusRing }}">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Cart
                    </a>
                </section>
            </aside>

        </div>
    </div>
</div>
