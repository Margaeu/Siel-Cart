<div>
    @php
        // Same dot + label treatment as the status column on My Orders and
        // the dashboard's recent orders, so a status reads the same everywhere.
        [$statusDot, $statusText] = match ($order->status) {
            'completed', 'return_completed' => ['bg-emerald-500', 'text-emerald-700'],
            'ready_for_pickup'              => ['bg-purple-500', 'text-purple-700'],
            'processing'                    => ['bg-blue-500', 'text-blue-700'],
            'cancelled'                     => ['bg-red-500', 'text-red-700'],
            // Matches the Payment Status "Pending" colour below.
            'pending'                       => ['bg-amber-500', 'text-amber-700'],
            default                        => ['bg-gray-400', 'text-gray-700'],
        };

        $paymentMethodLabel = match ($order->payment_method) {
            'cash_on_pickup' => 'Cash on Pickup',
            default          => Str::headline($order->payment_method),
        };

        $paymentStatus = strtolower($order->status) === 'cancelled'
            ? 'cancelled'
            : $order->payment_status;

        [$paymentDot, $paymentText] = match ($paymentStatus) {
            'paid'      => ['bg-emerald-500', 'text-emerald-700'],
            'cancelled' => ['bg-red-500', 'text-red-700'],
            default     => ['bg-amber-500', 'text-amber-700'],
        };

        $cancellationReasonLabel = match ($order->cancellation_reason) {
            'customer_no_show' => 'The order was not collected during the scheduled pickup period.',
            'change_of_mind'    => 'Change of mind',
            'incorrect_items'   => 'Added wrong item or quantity',
            null, ''            => 'No cancellation reason was recorded.',
            default             => Str::headline($order->cancellation_reason),
        };

        // Shared chrome, matching the cart, checkout, and account pages.
        $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2';
        $card = 'bg-white rounded-2xl shadow-sm border border-gray-100';
        $field = 'rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 min-w-0';
        $fieldLabel = 'text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-gray-400';
        $fieldValue = 'text-sm font-semibold text-gray-900 mt-0.5';
        $pending = 'text-gray-500 font-normal';
    @endphp

    <div class="bg-gray-50 min-h-screen py-10">
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
                        <nav class="mb-3 text-xs text-gray-500" aria-label="Breadcrumb">
                            <ol class="flex items-center gap-1.5">
                                <li><a href="{{ route('customer.dashboard') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">My Account</a></li>
                                <li aria-hidden="true">/</li>
                                <li><a href="{{ route('customer.orders') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">My Orders</a></li>
                                <li aria-hidden="true">/</li>
                                <li class="font-medium text-gray-700" aria-current="page">{{ $order->order_number }}</li>
                            </ol>
                        </nav>

                        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                            <div class="min-w-0">
                                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Order Details</h1>
                                <p class="text-xs text-gray-400 mt-1">
                                    Placed {{ $order->created_at->format('F j, Y') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-gray-200 px-3.5 py-1.5 text-xs font-semibold {{ $statusText }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}" aria-hidden="true"></span>
                                    {{ Str::headline($order->status) }}
                                </span>

                                {{-- Cancellation Modal for Pending Orders --}}
                                @livewire('cancel-order-modal', ['order' => $order])
                            </div>
                        </div>
                    </div>

                    @if(session()->has('order_success_title'))
                        <div class="flex items-start gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <div class="w-10 h-10 rounded-xl bg-[var(--color-primary)] text-white flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ session('order_success_title') }}</p>
                                <p class="text-xs text-gray-600 mt-1">{{ session('order_success_message') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Grid Content --}}
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                        <div class="xl:col-span-2 space-y-6">

                            {{-- Order Information --}}
                            <section class="{{ $card }} p-6">
                                <h2 class="text-base font-bold text-gray-900 mb-4">Order Information</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Order Number</p>
                                        <p class="{{ $fieldValue }}">{{ $order->order_number }}</p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Order Date</p>
                                        <p class="{{ $fieldValue }}">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Payment Status</p>
                                        <p class="inline-flex items-center gap-1.5 text-sm font-semibold mt-0.5 {{ $paymentText }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $paymentDot }}" aria-hidden="true"></span>
                                            {{ ucfirst($paymentStatus) }}
                                        </p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Payment Method</p>
                                        <p class="{{ $fieldValue }}">{{ $paymentMethodLabel }}</p>
                                    </div>
                                </div>
                            </section>

                            {{-- Customer Information --}}
                            <section class="{{ $card }} p-6">
                                <h2 class="text-base font-bold text-gray-900 mb-4">Customer Information</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Name</p>
                                        <p class="{{ $fieldValue }}">{{ $order->customer->name }}</p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Email</p>
                                        <p class="{{ $fieldValue }} truncate">{{ $order->customer->email }}</p>
                                    </div>
                                </div>
                            </section>

                            {{-- Order Items --}}
                            <section class="{{ $card }} p-6">
                                <h2 class="text-base font-bold text-gray-900 mb-2">Order Items</h2>
                                <div class="divide-y divide-gray-100">
                                    @foreach($order->items as $item)
                                        <div class="flex gap-4 py-5 last:pb-0">
                                            <div class="size-20 shrink-0 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                                                @if($item->display_image_url)
                                                    <img
                                                        src="{{ $item->display_image_url }}"
                                                        alt="{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }}"
                                                        class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-xl font-semibold">
                                                        {{ substr($item->product_name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-semibold text-gray-900 leading-snug">{{ $item->product_name }}</h3>
                                                @if($item->variant_name)
                                                    <p class="text-xs text-gray-500 mt-0.5">Variation: {{ $item->variant_name }}</p>
                                                @endif
                                                @if($item->product_sku)
                                                    <p class="text-[0.6875rem] text-gray-400 mt-0.5">SKU: {{ $item->product_sku }}</p>
                                                @endif
                                                <p class="text-sm text-gray-500 mt-1.5">Quantity: {{ $item->quantity }} × ₱{{ number_format($item->price, 2) }}</p>

                                                {{-- Outcomes UBAP recorded for this line. Admin notes stay internal. --}}
                                                @foreach($item->resolutions as $resolution)
                                                    @php($isRefund = $resolution->type === \App\Enums\OrderItemResolutionType::Refund)
                                                    <div class="mt-3 rounded-xl border px-3.5 py-2.5 text-xs space-y-0.5 {{ $isRefund ? 'border-amber-200 bg-amber-50' : 'border-sky-200 bg-sky-50' }}">
                                                        <p class="text-[0.6875rem] font-bold uppercase tracking-[0.12em] {{ $isRefund ? 'text-amber-800' : 'text-sky-800' }}">
                                                            {{ $resolution->type->getOutcomeLabel() }}
                                                            @if($item->quantity > 1)
                                                                <span class="font-medium normal-case tracking-normal">({{ $resolution->quantity }} of {{ $item->quantity }})</span>
                                                            @endif
                                                        </p>
                                                        <p class="text-gray-700">Reason: {{ $resolution->reason->getLabel() }}</p>
                                                        @if($isRefund)
                                                            <p class="text-gray-700">Refund Amount: ₱{{ number_format($resolution->refund_amount, 2) }}</p>
                                                        @else
                                                            <p class="text-gray-700">Replacement: {{ $resolution->replacement_label }}</p>
                                                        @endif
                                                        <p class="text-gray-500">Processed: {{ $resolution->processed_at->format('F j, Y') }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="font-bold text-gray-900">₱{{ number_format($item->subtotal, 2) }}</p>
                                                @if(strtolower($order->status) === 'completed' && $item->product_id)
                                                    @if(in_array($item->product_id, $reviewedProductIds))
                                                        <p class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 mt-2">
                                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                            Reviewed
                                                        </p>
                                                    @elseif($item->product)
                                                        <a href="{{ route('products.show', $item->product->slug) }}?tab=reviews"
                                                           class="mt-2 inline-flex min-h-9 items-center rounded-full bg-[var(--color-secondary)] px-3.5 text-xs font-semibold text-gray-900 transition hover:opacity-90 {{ $focusRing }}">
                                                            Review Product
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if(in_array($order->status, ['completed'], true))
                                    <p class="mt-5 pt-5 border-t border-gray-100 text-xs text-gray-500 leading-relaxed">
                                        For return, refund, or exchange concerns, you may contact UBAP via email or visit the UBAP Office directly.
                                        <a href="mailto:ubap@clsu.edu.ph" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">ubap@clsu.edu.ph</a>.
                                    </p>
                                @endif
                            </section>

                            {{-- Pickup Information --}}
                            <section class="{{ $card }} p-6">
                                <h2 class="text-base font-bold text-gray-900 mb-4">Pickup Information</h2>

                                {{-- Claim number leads: it is what the customer actually presents. --}}
                                <div class="flex items-start gap-3 rounded-xl border p-4 mb-3 {{ $order->claim_number ? 'border-[#FDE5A3] bg-[#FFFDF4]' : 'border-gray-100 bg-gray-50' }}">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $order->claim_number ? 'bg-[#F8A825] text-white' : 'bg-white border border-gray-100 text-gray-400' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="{{ $fieldLabel }}">Claim Number</p>
                                        @if($order->claim_number)
                                            <p class="font-bold text-lg text-gray-900">{{ $order->claim_number }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                Present this at the {{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL }} to collect your order.
                                            </p>
                                        @elseif($order->status === 'cancelled')
                                            <p class="text-sm text-gray-500 mt-0.5">Not issued — this order was cancelled.</p>
                                        @else
                                            <p class="text-sm text-gray-500 mt-0.5">Will be issued once your order is ready to collect.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="{{ $field }} sm:col-span-2">
                                        <p class="{{ $fieldLabel }}">Pickup Location</p>
                                        <p class="{{ $fieldValue }}">{{ \App\Livewire\CheckoutPage::PICKUP_LOCATION_LABEL }}</p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Pickup Date</p>
                                        <p class="{{ $fieldValue }}">
                                            @if($order->pickup_date)
                                                {{ $order->pickup_date->format('M d, Y') }}
                                            @else
                                                <span class="{{ $pending }}">To be scheduled</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Pickup Time</p>
                                        <p class="{{ $fieldValue }}">
                                            @if($order->pickup_slot)
                                                {{ $order->pickup_slot }}
                                            @else
                                                <span class="{{ $pending }}">To be scheduled</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Claimed By</p>
                                        <p class="{{ $fieldValue }}">
                                            @if($order->claimant_name)
                                                {{ $order->claimant_name }}
                                            @else
                                                <span class="{{ $pending }}">To be designated</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="{{ $field }}">
                                        <p class="{{ $fieldLabel }}">Contact Number</p>
                                        <p class="{{ $fieldValue }}">
                                            @if($order->claimant_phone)
                                                {{ $order->claimant_phone }}
                                            @else
                                                <span class="{{ $pending }}">To be designated</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </section>

                        </div>

                        {{-- Order Summary --}}
                        <aside class="xl:sticky xl:top-24">
                            <section class="{{ $card }} p-6">
                                <h2 class="text-base font-bold text-gray-900 mb-5">Order Summary</h2>

                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500">Merchandise Subtotal</span>
                                        <span class="font-medium text-gray-900">₱{{ number_format($order->subtotal, 2) }}</span>
                                    </div>

                                    {{-- 1. Display Completed / Collected At Date --}}
                                    @if(strtolower($order->status) === 'completed')
                                        <div class="flex justify-between items-center gap-3 border-t border-gray-100 pt-3">
                                            <span class="text-gray-500">Collected At</span>
                                            <span class="font-semibold text-emerald-700 text-right">
                                                {{ $order->completed_at ? $order->completed_at->format('M d, Y h:i A') : $order->updated_at->format('M d, Y h:i A') }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- 2. Display Cancelled Date --}}
                                    @if(strtolower($order->status) === 'cancelled')
                                        <div class="flex justify-between items-center gap-3 border-t border-gray-100 pt-3">
                                            <span class="text-gray-500">Cancelled On</span>
                                            <span class="font-semibold text-red-600 text-right">
                                                {{ $order->cancelled_at ? $order->cancelled_at->format('M d, Y h:i A') : $order->updated_at->format('M d, Y h:i A') }}
                                            </span>
                                        </div>
                                        <div class="rounded-xl border border-red-100 bg-red-50 px-3.5 py-2.5">
                                            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-red-400">Cancellation Reason</p>
                                            <p class="mt-0.5 text-sm font-semibold text-red-700">
                                                {{ $cancellationReasonLabel }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <div class="border-t border-gray-100 mt-5 pt-5">
                                    <div class="flex justify-between items-baseline">
                                        <span class="font-semibold text-gray-900">Order Total</span>
                                        <span class="text-2xl font-extrabold tracking-[-0.02em] text-[var(--color-primary)]">
                                            ₱{{ number_format($order->total, 2) }}
                                        </span>
                                    </div>
                                </div>

                                <a href="{{ route('customer.orders') }}"
                                   class="mt-5 flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-6 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 {{ $focusRing }}">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Back to My Orders
                                </a>
                            </section>
                        </aside>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
