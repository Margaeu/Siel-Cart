<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-sm font-bold tracking-wider text-[#E0A70D] uppercase mb-1">My Account</h1>
            <h2 class="text-3xl font-bold text-[#1E6031]">Welcome back, {{ auth('customer')->user()->first_name }}</h2>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-[#1E6031]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Orders</p>
                        <p class="text-3xl font-bold text-[#1E6031]">{{ $stats['total_orders'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-[#1E6031] rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Pending Orders -->
            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-[#E0A70D]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Pending Orders</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['pending_orders'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-[#FEF8EA] text-[#E0A70D] rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-[#1E6031]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Spent</p>
                        <p class="text-3xl font-bold text-[#1E6031]">${{ number_format($stats['total_spent'], 2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-[#1E6031] rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Quick Actions --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-[#1E6031]">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <!-- My Orders -->
                        <a href="{{ route('customer.orders') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-emerald-50/50 transition group">
                            <div class="w-10 h-10 bg-emerald-100/70 text-[#1E6031] rounded-lg flex items-center justify-center group-hover:bg-[#1E6031] group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 group-hover:text-[#1E6031] transition">My Orders</p>
                                <p class="text-sm text-gray-600">View order history</p>
                            </div>
                        </a>

                        <!-- My Profile -->
                        <a href="{{ route('customer.profile') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-emerald-50/50 transition group">
                            <div class="w-10 h-10 bg-emerald-100/70 text-[#1E6031] rounded-lg flex items-center justify-center group-hover:bg-[#1E6031] group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 group-hover:text-[#1E6031] transition">My Profile</p>
                                <p class="text-sm text-gray-600">Manage account details</p>
                            </div>
                        </a>

                        <!-- Continue Shopping -->
                        <a href="{{ route('products.index') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-[#FEF8EA] transition group">
                            <div class="w-10 h-10 bg-[#FEF8EA] text-[#E0A70D] rounded-lg flex items-center justify-center group-hover:bg-[#E0A70D] group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 group-hover:text-[#E0A70D] transition">Continue Shopping</p>
                                <p class="text-sm text-gray-600">Browse products</p>
                            </div>
                        </a>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-red-50 transition group text-left">
                                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-lg flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 group-hover:text-red-600 transition">Logout</p>
                                    <p class="text-sm text-gray-600">Sign out of account</p>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Recent Orders --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-[#1E6031]">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Recent Orders</h2>
                        <a href="{{ route('customer.orders') }}" 
                           class="text-[#1E6031] hover:text-[#E0A70D] font-semibold text-sm transition">
                            View All →
                        </a>
                    </div>

                    @if($recentOrders->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentOrders as $order)
                                <a href="{{ route('customer.orders.show', $order->id) }}"
                                   class="block border border-gray-200 rounded-lg p-4 hover:border-[#1E6031] hover:shadow-md transition">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                                            <p class="text-sm text-gray-600">{{ $order->created_at->format('M d, Y') }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-[#1E6031]">${{ number_format($order->total, 2) }}</p>
                                            <span class="inline-block px-2.5 py-1 text-xs font-medium rounded-full {{ 
                                                $order->status === 'delivered' ? 'bg-emerald-100 text-[#1E6031]' : 
                                                ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : 
                                                'bg-[#FEF8EA] text-[#E0A70D]') 
                                            }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @foreach($order->items->take(3) as $item)
                                            @if($item->product)
                                                <div class="w-12 h-12 rounded bg-gray-100 border border-gray-200 overflow-hidden">
                                                    @if($item->product->primaryImage)
                                                        <img src="{{ asset('storage/' . $item->product->primaryImage->image_path) }}" 
                                                             alt="{{ $item->product_name }}"
                                                             class="w-full h-full object-cover">
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        @if($order->items->count() > 3)
                                            <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                +{{ $order->items->count() - 3 }} more
                                            </span> 
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-emerald-50 text-[#1E6031] rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <p class="text-gray-600 mb-4">No orders yet</p>
                            <a href="{{ route('products.index') }}" 
                               class="inline-block bg-[#1E6031] text-white px-6 py-2.5 rounded-lg hover:bg-[#164724] active:bg-[#0f3018] font-semibold transition shadow-sm">
                                Start Shopping
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>