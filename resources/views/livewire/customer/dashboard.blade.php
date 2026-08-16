<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <h1>My Account</h1>
            <h1>Welcome back, {{ auth('customer')->user()->name }}</h1>
        </div>

        {{-- stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Orders</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['total_orders'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 text-blue-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Pending Orders</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['pending_orders'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Spent</p>
                        <p class="text-3xl font-bold text-gray-900">${{ number_format($stats['total_spent'], 2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- quick actions --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="{{ route('customer.orders') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-10 h-10 bg-indigo-100 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">My Orders</p>
                                <p class="text-sm text-gray-600">View order history</p>
                            </div>
                        </a>

                        <a href="{{ route('customer.profile') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-10 h-10 bg-indigo-100 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">My Profile</p>
                                <p class="text-sm text-gray-600">Manage account details</p>
                            </div>
                        </a>

                        <a href="{{ route('products.index') }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-10 h-10 bg-indigo-100 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Continue Shopping</p>
                                <p class="text-sm text-gray-600">Browse products</p>
                            </div>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-red-50 transition group">
                                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-lg flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <p class="font-semibold text-gray-900">Logout</p>
                                    <p class="text-sm text-gray-600">Sign out of account</p>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Recent Orders --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Recent Orders</h2>
                        <a href="{{ route('customer.orders') }}" 
                           class="text-blue-600 hover:text-indigo-700 font-medium text-sm">
                            View All →
                        </a>
                    </div>

                    @if($recentOrders->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentOrders as $order)
                                <a href="{{ route('customer.orders.show', $order->id) }}"
                                   class="block border rounded-lg p-4 hover:border-blue-600 hover:shadow-md transition">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                                            <p class="text-sm text-gray-600">{{ $order->created_at->format('M d, Y') }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-gray-900">${{ number_format($order->total, 2) }}</p>
                                            <span class="inline-block px-2 py-1 text-xs rounded {{ 
                                                $order->status === 'delivered' ? 'bg-green-100 text-green-800' : 
                                                ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : 
                                                'bg-yellow-100 text-yellow-800') 
                                            }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @foreach($order->items->take(3) as $item)
                                            @if($item->product)
                                                <div class="w-12 h-12 rounded bg-gray-100 overflow-hidden">
                                                    @if($item->product->primaryImage)
                                                        <img src="{{ asset('storage/' . $item->product->primaryImage->image_path) }}" 
                                                             alt="{{ $item->product_name }}"
                                                             class="w-full h-full object-cover">
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        @if($order->items->count() > 3)
                                            <span class="text-sm text-gray-600">+{{ $order->items->count() - 3 }} more</span> 
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <p class="text-gray-600 mb-4">No orders yet</p>
                            <a href="{{ route('products.index') }}" 
                               class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                                Start Shopping
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        
        
    </div>
</div>