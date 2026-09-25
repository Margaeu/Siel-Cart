<div class="bg-gray-50  min-h-screen py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- Left Sidebar --}}
            <aside class="lg:col-span-3">
                @include('partials.customer-account-sidebar', ['active' => 'overview'])
            </aside>

            {{-- Main Content Column --}}
            <main class="lg:col-span-9 space-y-6">

                {{-- Welcome Header --}}
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                    <div>
                        <span class="text-xs font-semibold tracking-wider text-gray-400 uppercase">My Account</span>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                            Welcome back, {{ auth('customer')->user()->first_name }}
                        </h1>
                    </div>
                    <span class="text-xs text-gray-400">
                        Member since {{ auth('customer')->user()->created_at->format('F Y') }}
                    </span>
                </div>

                {{-- Order Ready Banner (Optional / Conditional) --}}
                @if(isset($readyForPickupOrder))
                    <div class="bg-[#FFFDF4] border border-[#FDE5A3] rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#F8A825] text-white flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm">
                                    Your order {{ $readyForPickupOrder->order_number }} is ready for pickup
                                    @if($readyForPickupOrder->reschedule_count > 0)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 align-middle text-[0.6875rem] font-semibold text-amber-800">Rescheduled</span>
                                    @endif
                                </h4>
                                <div class="flex flex-wrap items-center gap-x-3 text-xs text-gray-500 mt-1">
                                    @if($readyForPickupOrder->claim_number)
                                        <span>Claim no. <strong class="text-gray-700 font-semibold">{{ $readyForPickupOrder->claim_number }}</strong></span>
                                        <span>•</span>
                                    @endif
                                    <span>
                                        UBAP Office
                                        @if($readyForPickupOrder->pickup_date)
                                            · <strong class="text-gray-700 font-semibold">{{ $readyForPickupOrder->pickup_date->format('M d, Y') }}</strong>
                                        @endif
                                        @if($readyForPickupOrder->pickup_slot)
                                            · {{ $readyForPickupOrder->pickup_slot }}
                                        @endif
                                    </span>
                                    <span>•</span>
                                    <span>Pay in cash: <strong class="text-gray-800 font-semibold">₱{{ number_format($readyForPickupOrder->total, 2) }}</strong></span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('customer.orders.show', $readyForPickupOrder->id) }}"
                           class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-[var(--color-primary)] px-4 text-xs font-semibold text-white transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 sm:w-auto sm:shrink-0">
                            View order
                        </a>
                    </div>
                @endif

                {{-- Status Pipeline Tabs / Carousel on Shrink --}}
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <h2 class="text-base font-bold text-gray-900">My Orders</h2>
                    </div>

                    {{-- Horizontal scrollable carousel on mobile, 5-col grid on sm+ --}}
                    <div class="flex sm:grid sm:grid-cols-5 gap-3 sm:gap-4 overflow-x-auto sm:overflow-visible pb-3 sm:pb-0 snap-x snap-mandatory scroll-smooth sm:divide-x sm:divide-gray-100 -mx-2 px-2 sm:mx-0 sm:px-0">
                        {{-- Pending --}}
                        <a href="{{ route('customer.orders', ['status' => 'pending']) }}" wire:navigate
                           class="group flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[6rem] sm:min-w-0 snap-center rounded-xl transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center transition group-hover:bg-emerald-50 group-hover:text-[var(--color-primary)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                @if(($stats['pending_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-gray-800 text-white text-[0.625rem] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['pending_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap transition group-hover:text-gray-900">Pending</span>
                        </a>

                        {{-- Processing --}}
                        <a href="{{ route('customer.orders', ['status' => 'processing']) }}" wire:navigate
                           class="group flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[6rem] sm:min-w-0 snap-center rounded-xl transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center transition group-hover:bg-emerald-50 group-hover:text-[var(--color-primary)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                @if(($stats['processing_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-gray-800 text-white text-[0.625rem] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['processing_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap transition group-hover:text-gray-900">Processing</span>
                        </a>

                        {{-- Ready for Pickup --}}
                        <a href="{{ route('customer.orders', ['status' => 'ready_for_pickup']) }}" wire:navigate
                           class="group flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[7rem] sm:min-w-0 snap-center rounded-xl transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center transition group-hover:bg-emerald-50 group-hover:text-[var(--color-primary)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                @if(($stats['ready_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-gray-800 text-white text-[0.625rem] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['ready_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap transition group-hover:text-gray-900">Ready for Pickup</span>
                        </a>

                        {{-- Completed --}}
                        <a href="{{ route('customer.orders', ['status' => 'completed']) }}" wire:navigate
                           class="group flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[6rem] sm:min-w-0 snap-center rounded-xl transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center transition group-hover:bg-emerald-50 group-hover:text-[var(--color-primary)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                @if(($stats['completed_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-gray-800 text-white text-[0.625rem] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['completed_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap transition group-hover:text-gray-900">Completed</span>
                        </a>

                        {{-- Cancelled --}}
                        <a href="{{ route('customer.orders', ['status' => 'cancelled']) }}" wire:navigate
                           class="group flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[6rem] sm:min-w-0 snap-center rounded-xl transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center transition group-hover:bg-emerald-50 group-hover:text-[var(--color-primary)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                @if(($stats['cancelled_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-gray-800 text-white text-[0.625rem] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['cancelled_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap transition group-hover:text-gray-900">Cancelled</span>
                        </a>
                    </div>
                </div>

                {{-- Recent Orders List --}}
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-base font-bold text-gray-900">Recent orders</h2>
                        <a href="{{ route('customer.orders') }}" class="text-xs font-medium text-gray-500 hover:text-[var(--color-primary)] transition">
                            See all
                        </a>
                    </div>

                    @if($recentOrders->count() > 0)
                        <div class="divide-y divide-gray-100">
                            @foreach($recentOrders as $order)
                                <a href="{{ route('customer.orders.show', $order->id) }}" 
                                   class="py-4 first:pt-0 last:pb-0 flex items-center justify-between gap-4 hover:bg-gray-50/70 -mx-4 px-4 rounded-xl transition">
                                    
                                    {{-- Left: Item Thumbnails + Order Info --}}
                                    <div class="flex items-center gap-4 min-w-0">
                                        {{-- Thumbnails --}}
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            @foreach($order->items->take(3) as $item)
                                                <div class="w-10 h-10 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden flex items-center justify-center">
                                                    @if($item->display_image_url)
                                                        <img src="{{ $item->display_image_url }}" 
                                                             alt="{{ $item->product_name }}" 
                                                             class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-xs text-gray-400 font-semibold">
                                                            {{ substr($item->product_name, 0, 1) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if($order->items->count() > 3)
                                                <span class="text-[0.6875rem] font-medium text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                                    +{{ $order->items->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Order Code, Product Names & Date --}}
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 text-sm leading-tight">
                                                {{ $order->order_number }}
                                            </p>
                                            <p class="text-xs text-gray-500 truncate max-w-xs sm:max-w-md mt-0.5">
                                                {{ $order->items->pluck('product_name')->implode(', ') }}
                                            </p>
                                            <p class="text-[0.6875rem] text-gray-400 mt-0.5">
                                                Placed {{ $order->created_at->format('M d, Y') }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Right: Status Pill & Price --}}
                                    <div class="flex items-center gap-6 shrink-0 text-right">
                                        {{-- Status Pill with Colored Dot --}}
                                        <div class="hidden sm:inline-flex items-center gap-1.5 text-xs font-medium">
                                            @php
                                                $statusDot = match(strtolower($order->status)) {
                                                    'completed' => 'bg-emerald-500 text-emerald-700',
                                                    'ready_for_pickup', 'ready for pickup', 'ready' => 'bg-purple-500 text-purple-700',
                                                    'processing' => 'bg-blue-500 text-blue-700',
                                                    'cancelled' => 'bg-red-500 text-red-700',
                                                    'pending' => 'bg-amber-500 text-amber-700',
                                                    default => 'bg-gray-400 text-gray-700',
                                                };
                                                $dotColor = explode(' ', $statusDot)[0];
                                                $textColor = explode(' ', $statusDot)[1];
                                            @endphp
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                            <span class="{{ $textColor }}">{{ Str::headline($order->status) }}</span>
                                        </div>

                                        {{-- Price & Quantity --}}
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm">
                                                ₱{{ number_format($order->total, 2) }}
                                            </p>
                                            <p class="text-[0.6875rem] text-gray-400">
                                                {{ $order->items->sum('quantity') ?? $order->items->count() }} items
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-10">
                            <p class="text-sm text-gray-500 mb-3">No orders yet</p>
                            <a href="{{ route('products.index') }}"
                               class="inline-flex min-h-11 items-center rounded-lg bg-[var(--color-primary)] px-4 text-xs font-semibold text-white transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2">
                                Start shopping
                            </a>
                        </div>
                    @endif
                </div>

            </main>
        </div>
    </div>
</div>