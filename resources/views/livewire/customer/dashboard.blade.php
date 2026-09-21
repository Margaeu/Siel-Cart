<div class="bg-gray-50  min-h-screen py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- Left Sidebar --}}
            <aside class="lg:col-span-3">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    {{-- User Mini Profile --}}
                    <div class="flex items-center gap-3.5 pb-6 border-b border-gray-100">
                        <div class="w-12 h-12 rounded-full bg-[var(--color-primary)] text-white font-bold flex items-center justify-center text-sm tracking-wide shrink-0">
                            {{ strtoupper(substr(auth('customer')->user()->first_name ?? 'U', 0, 1) . substr(auth('customer')->user()->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 leading-tight truncate">
                                {{ auth('customer')->user()->first_name }} {{ auth('customer')->user()->last_name }}
                            </h3>
                            <a href="{{ route('customer.profile') }}" class="text-xs text-gray-500 hover:text-[var(--color-primary)] transition">
                                Edit profile
                            </a>
                        </div>
                    </div>

                    {{-- Navigation Links --}}
                    <nav class="mt-6 space-y-1">
                        {{-- Active Link: Overview --}}
                        <a href="{{ route('customer.dashboard') }}" 
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold bg-emerald-50 text-[var(--color-primary)] border-l-4 border-[var(--color-primary)] transition">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span>Overview</span>
                        </a>

                        {{-- My Orders --}}
                        <a href="{{ route('customer.orders') }}" 
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                            <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span>My Orders</span>
                        </a>

                        {{-- Profile --}}
                        <a href="{{ route('customer.profile') }}" 
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                            <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>Profile</span>
                        </a>

                        {{-- Password --}}
                        <a href="{{ route('customer.profile') }}#password" 
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                            <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span>Password</span>
                        </a>

                        <div class="pt-4 mt-4 border-t border-gray-100 space-y-1">
                            {{-- Continue Shopping --}}
                            <a href="{{ route('products.index') }}" 
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span>Continue shopping</span>
                            </a>

                            {{-- Log out --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" 
                                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 transition text-left">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <span>Log out</span>
                                </button>
                            </form>
                        </div>
                    </nav>
                </div>
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
                                </h4>
                                <div class="flex flex-wrap items-center gap-x-3 text-xs text-gray-500 mt-1">
                                    @if($readyForPickupOrder->claim_code)
                                        <span>Claim no. <strong class="text-gray-700 font-semibold">{{ $readyForPickupOrder->claim_code }}</strong></span>
                                        <span>•</span>
                                    @endif
                                    <span>UBAP Office · 8:00 AM – 5:00 PM</span>
                                    <span>•</span>
                                    <span>Pay in cash: <strong class="text-gray-800 font-semibold">₱{{ number_format($readyForPickupOrder->total, 2) }}</strong></span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('customer.orders.show', $readyForPickupOrder->id) }}" 
                           class="inline-flex justify-center items-center px-4 py-2 bg-[var(--color-primary)] text-white text-xs font-semibold rounded-lg hover:opacity-90 transition shrink-0">
                            View order
                        </a>
                    </div>
                @endif

                {{-- Status Pipeline Tabs / Carousel on Shrink --}}
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4 sm:mb-6">
                        <h2 class="text-base font-bold text-gray-900">My Orders</h2>
                        <a href="{{ route('customer.orders') }}" class="text-xs font-medium text-gray-500 hover:text-[var(--color-primary)] transition">
                            View all orders &rarr;
                        </a>
                    </div>

                    {{-- Horizontal scrollable carousel on mobile, 5-col grid on sm+ --}}
                    <div class="flex sm:grid sm:grid-cols-5 gap-3 sm:gap-4 overflow-x-auto sm:overflow-visible pb-3 sm:pb-0 snap-x snap-mandatory scroll-smooth sm:divide-x sm:divide-gray-100 -mx-2 px-2 sm:mx-0 sm:px-0">
                        {{-- Pending --}}
                        <div class="flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[95px] sm:min-w-0 snap-center">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                @if(($stats['pending_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['pending_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Pending</span>
                        </div>

                        {{-- Processing --}}
                        <div class="flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[95px] sm:min-w-0 snap-center">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-emerald-50 text-[var(--color-primary)] flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                @if(($stats['processing_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['processing_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Processing</span>
                        </div>

                        {{-- Ready for Pickup --}}
                        <div class="flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[110px] sm:min-w-0 snap-center">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                @if(($stats['ready_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['ready_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Ready for Pickup</span>
                        </div>

                        {{-- Completed --}}
                        <div class="flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[95px] sm:min-w-0 snap-center">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                @if(($stats['completed_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-emerald-600 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['completed_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Completed</span>
                        </div>

                        {{-- Cancelled --}}
                        <div class="flex flex-col items-center justify-center text-center p-2 shrink-0 min-w-[95px] sm:min-w-0 snap-center">
                            <div class="relative mb-2">
                                <div class="w-11 h-11 rounded-full bg-red-50 text-red-500 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                @if(($stats['cancelled_orders'] ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold shadow-sm">
                                        {{ $stats['cancelled_orders'] }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Cancelled</span>
                        </div>
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
                                                <span class="text-[11px] font-medium text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
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
                                            <p class="text-[11px] text-gray-400 mt-0.5">
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
                                                    'ready for pickup', 'ready' => 'bg-amber-500 text-amber-700',
                                                    'processing' => 'bg-blue-500 text-blue-700',
                                                    'cancelled' => 'bg-red-500 text-red-700',
                                                    default => 'bg-gray-400 text-gray-700',
                                                };
                                                $dotColor = explode(' ', $statusDot)[0];
                                                $textColor = explode(' ', $statusDot)[1];
                                            @endphp
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                            <span class="{{ $textColor }}">{{ ucfirst($order->status) }}</span>
                                        </div>

                                        {{-- Price & Quantity --}}
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm">
                                                ₱{{ number_format($order->total, 2) }}
                                            </p>
                                            <p class="text-[11px] text-gray-400">
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
                               class="inline-block bg-[var(--color-primary)] text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition">
                                Start shopping
                            </a>
                        </div>
                    @endif
                </div>

            </main>
        </div>
    </div>
</div>