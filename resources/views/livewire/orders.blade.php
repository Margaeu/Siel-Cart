<div class="bg-gray-50 min-h-screen py-10">
@php
    $statusTabs = [
        '' => 'All',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
@endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- Left Sidebar --}}
            <aside class="lg:col-span-3">
                @include('partials.customer-account-sidebar', ['active' => 'orders'])
            </aside>

            {{-- Main Content Column --}}
            <main class="lg:col-span-9 space-y-6">

                {{-- Header --}}
                <div>
                    <span class="text-xs font-semibold tracking-wider text-gray-400 uppercase">My Account</span>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">My Orders</h1>
                </div>

                {{-- Status Filter Tabs (scrollable on mobile) --}}
                <div class="bg-white rounded-2xl p-2 shadow-sm border border-gray-100">
                    <div class="flex gap-1 overflow-x-auto">
                        @foreach($statusTabs as $value => $label)
                            <button type="button"
                                    wire:click="$set('statusFilter', '{{ $value }}')"
                                    class="px-4 py-2 rounded-xl text-sm whitespace-nowrap transition {{ $statusFilter === $value
                                        ? 'bg-emerald-50 text-[var(--color-primary)] font-semibold'
                                        : 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-900' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Orders List --}}
                @if($orders->count() > 0)
                    <div class="space-y-4">
                        @foreach($orders as $order)
                            @php
                                [$dotColor, $textColor] = match ($order->status) {
                                    'completed' => ['bg-emerald-500', 'text-emerald-700'],
                                    'ready_for_pickup' => ['bg-purple-500', 'text-purple-700'],
                                    'processing' => ['bg-blue-500', 'text-blue-700'],
                                    'cancelled' => ['bg-red-500', 'text-red-700'],
                                    'pending' => ['bg-amber-500', 'text-amber-700'],
                                    default => ['bg-gray-400', 'text-gray-700'],
                                };
                            @endphp

                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                {{-- Order Header --}}
                                <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-x-8 gap-y-2">
                                        <div>
                                            <p class="text-[0.6875rem] font-semibold tracking-wider text-gray-400 uppercase">Order</p>
                                            <p class="font-bold text-gray-900 text-sm">{{ $order->order_number }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[0.6875rem] font-semibold tracking-wider text-gray-400 uppercase">Placed</p>
                                            <p class="font-semibold text-gray-700 text-sm">{{ $order->created_at->format('M d, Y') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[0.6875rem] font-semibold tracking-wider text-gray-400 uppercase">Total</p>
                                            <p class="font-bold text-gray-900 text-sm">₱{{ number_format($order->total, 2) }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-5">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                            <span class="{{ $textColor }}">{{ Str::headline($order->status) }}</span>
                                        </span>
                                        <a href="{{ route('customer.orders.show', $order->id) }}"
                                           class="text-xs font-medium text-gray-500 hover:text-[var(--color-primary)] transition">
                                            View details &rarr;
                                        </a>
                                    </div>
                                </div>

                                {{-- Order Items --}}
                                <div class="px-6 divide-y divide-gray-100">
                                    @foreach($order->items as $item)
                                        <div class="py-4 flex items-center gap-4">
                                            <div class="w-14 h-14 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden flex items-center justify-center shrink-0">
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

                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 text-sm truncate">{{ $item->product_name }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    @if($item->variant_name)
                                                        {{ $item->variant_name }} &middot;
                                                    @endif
                                                    Qty {{ $item->quantity }}
                                                </p>
                                                @if($item->resolutions->isNotEmpty())
                                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                                        @foreach($item->resolutions->pluck('type')->unique() as $type)
                                                            <span class="inline-block rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold {{ $type === \App\Enums\OrderItemResolutionType::Refund ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700' }}">
                                                                {{ $type->getOutcomeLabel() }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            <p class="font-bold text-gray-900 text-sm shrink-0">₱{{ number_format($item->subtotal, 2) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div>
                        {{ $orders->links() }}
                    </div>
                @else
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="text-center py-10">
                            <div class="w-11 h-11 mx-auto mb-3 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">No orders found</p>
                            <p class="text-sm text-gray-500 mt-1 mb-4">
                                @if($statusFilter)
                                    You have no {{ strtolower($statusTabs[$statusFilter] ?? Str::headline($statusFilter)) }} orders.
                                @else
                                    You haven't placed any orders yet.
                                @endif
                            </p>
                            @if($statusFilter)
                                <button type="button" wire:click="$set('statusFilter', '')"
                                        class="inline-block bg-[var(--color-primary)] text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition">
                                    Show all orders
                                </button>
                            @else
                                <a href="{{ route('products.index') }}"
                                   class="inline-block bg-[var(--color-primary)] text-white text-xs font-semibold px-4 py-2 rounded-lg hover:opacity-90 transition">
                                    Start shopping
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

            </main>
        </div>
    </div>
</div>
