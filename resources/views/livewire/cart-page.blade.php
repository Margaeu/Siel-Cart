@php
    // Shared control chrome, matching the catalog and account pages: every
    // interactive target clears the 44px minimum (min-h-11 / size-11) and
    // shows the same primary-coloured focus ring.
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2';
    $card = 'bg-white rounded-2xl shadow-sm border border-gray-100';

    $itemCount = $cart ? $cart->items->sum('quantity') : 0;
@endphp

{{--
    pendingSaves counts rows whose quantity changed on screen but is not saved
    yet (see the stepper below). Checkout waits for it to reach zero, so
    leaving the page cannot drop a quantity change that is still debouncing.
--}}
<div class="bg-gray-50 min-h-screen" x-data="{ pendingSaves: 0 }">
    <div class="mx-auto max-w-7xl px-4 pt-6 pb-12 sm:px-6 sm:pt-8 lg:px-8">

        {{-- Breadcrumb --}}
        <nav class="mb-3 text-xs text-gray-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">Home</a></li>
                <li aria-hidden="true">/</li>
                <li class="font-medium text-gray-700" aria-current="page">Shopping Cart</li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="mb-8">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-2xl font-extrabold tracking-[-0.02em] text-gray-900 sm:text-3xl">
                    Shopping Cart
                </h1>
            </div>

            {{-- Same gold-into-hairline rule as the catalog header. --}}
            <div class="mt-4 flex items-center" aria-hidden="true">
                <span class="h-[3px] w-14 rounded-full bg-[var(--color-secondary)]"></span>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>
        </div>

        @if($cart && $cart->items->count() > 0)

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                {{-- Cart Items --}}
                <div class="lg:col-span-8 space-y-4">

                    {{-- Flash Success Message --}}
                    @if (session()->has('success'))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Quantity Error Message --}}
                    @if(session('error'))
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($quantityError)
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $quantityError }}
                        </div>
                    @endif

                    {{-- confirmClear is client-side only: opening the dialog costs no server round trip, and nothing is cleared until "Yes, clear cart" calls clearCart. --}}
                    <div class="{{ $card }} p-6" x-data="{ confirmClear: false }" @keydown.escape.window="confirmClear = false">
                        {{-- The rule under the heading separates it from the item rows, which only get dividers between themselves. --}}
                        <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-2">
                            <h2 class="text-base font-bold text-gray-900">Items in your cart</h2>
                            {{-- Red warning badge: clearing empties the whole cart, so it should not read as a quiet text link. --}}
                            <button
                                type="button"
                                @click="confirmClear = true"
                                class="inline-flex min-h-11 items-center rounded-full border border-red-300 bg-red-100 px-3.5 text-xs font-semibold text-red-800 transition hover:bg-red-200 {{ $focusRing }}">
                                Clear cart
                            </button>
                        </div>

                        {{--
                            Replaces the browser's native wire:confirm popup. Kept inside
                            the component (not x-teleport'd to <body>) because wire:click
                            resolves its component from the DOM ancestry, which a
                            teleported node no longer has. It is position:fixed, so where
                            it sits in the markup does not affect where it shows.
                        --}}
                        <div
                            x-show="confirmClear"
                            x-cloak
                            x-transition.opacity.duration.200ms
                            @click.self="confirmClear = false"
                            class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/50 p-4 backdrop-blur-sm sm:items-center"
                            role="alertdialog"
                            aria-modal="true"
                            aria-labelledby="clear-cart-title"
                            aria-describedby="clear-cart-description">
                            <div
                                x-show="confirmClear"
                                x-trap.noscroll="confirmClear"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                                x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                                x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                                class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl sm:p-8">
                                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-red-100 text-red-600" aria-hidden="true">
                                    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </div>

                                <h3 id="clear-cart-title" class="mt-5 text-lg font-bold text-gray-900">Clear your cart?</h3>
                                <p id="clear-cart-description" class="mt-2 text-sm leading-relaxed text-gray-600">
                                    This will remove all {{ $itemCount }} {{ Str::plural('item', $itemCount) }} from your cart. This can't be undone.
                                </p>

                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                                    <button
                                        type="button"
                                        @click="confirmClear = false"
                                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:min-w-32 {{ $focusRing }}">
                                        Keep items
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="clearCart"
                                        wire:loading.attr="disabled"
                                        wire:target="clearCart"
                                        @click="confirmClear = false"
                                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60 sm:min-w-32 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                                        Yes, clear cart
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="divide-y divide-gray-100">
                            @foreach($cart->items as $item)

                                @php
                                    /*
                                     * Stock and availability both come from the CartItem
                                     * model, which is the same rule CartService uses to
                                     * decide whether checkout may proceed.
                                     */
                                    $availableStock = $item->available_stock;
                                    $isUnavailable  = $item->is_unavailable;

                                    /*
                                     * An over-stock item is still sold, the cart just
                                     * holds more than is left. The customer can fix that
                                     * by lowering the quantity, so the minus control
                                     * stays usable for it.
                                     */
                                    $isOverStock = $item->is_over_stock;

                                    // Use the selected variant's first image.
                                    // Fall back to the product's general primary image.
                                    $cartImage = $item->variant?->images->first()
                                        ?? $item->product->primaryImage;
                                @endphp

                                {{--
                                    wire:key lets Livewire track each row so that
                                    removing one does not leave the next row showing
                                    the removed row's values.

                                    Only a row the customer cannot fix is dimmed. An
                                    over-stock row stays fully legible because they are
                                    expected to act on it.
                                --}}
                                <div wire:key="cart-item-{{ $item->id }}"
                                     class="py-5 last:pb-0 flex gap-4 {{ $isUnavailable && !$isOverStock ? 'opacity-60' : '' }}">

                                    {{-- Product Image --}}
                                    <div class="size-20 sm:size-24 shrink-0 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                                        @if($cartImage)
                                            <img
                                                src="{{ $cartImage->url }}"
                                                alt="{{ $item->product->name }}{{ $item->variant ? ' - '.$item->variant->name : '' }}"
                                                class="w-full h-full object-cover
                                                    {{ $isUnavailable && !$isOverStock ? 'grayscale' : '' }}">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center">
                                                <span class="text-xl font-semibold text-gray-400">
                                                    {{ substr($item->product->name, 0, 1) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Product Info + Controls --}}
                                    <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:justify-between gap-3">

                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-900 leading-snug">
                                                {{ $item->product->name }}
                                            </h3>

                                            @if($item->variant)
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    {{ $item->variant->name }}
                                                </p>
                                            @endif

                                            {{--
                                                $item->price is the variant price when the
                                                item has a variant, and the product price
                                                when it does not.
                                            --}}
                                            <p class="text-sm font-semibold text-[var(--color-primary)] mt-1.5">
                                                @if($item->price !== null)
                                                    ₱{{ number_format($item->price, 2) }}
                                                    <span class="text-xs font-normal text-gray-400">each</span>
                                                @else
                                                    Price unavailable
                                                @endif
                                            </p>

                                            {{-- Availability Notice --}}
                                            @if($isOverStock)

                                                {{-- Fixable: tell them what is left. --}}
                                                <p class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 mt-2">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                                                    Only {{ $availableStock }} left in stock.
                                                    Lower the quantity to continue.
                                                </p>

                                            @elseif($isUnavailable)

                                                <p class="inline-flex items-center gap-1.5 text-xs font-medium text-red-700 mt-2">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0" aria-hidden="true"></span>
                                                    This product is currently unavailable.
                                                </p>

                                            @endif
                                        </div>

                                        {{--
                                            The stepper is driven by Alpine, not wire:click. Each
                                            tap used to be a full round trip that re-rendered the
                                            cart before the number moved, so it lagged about a
                                            second per tap. Now the number and the row total
                                            change on the spot and the save follows: taps are
                                            debounced into one updateQuantity() call, and typing
                                            is saved on blur/Enter.

                                            The server stays the authority. updateQuantity()
                                            returns the quantity the cart really holds, and once
                                            a save settles the stepper snaps to it -- so a
                                            quantity CartService rejects reverts to what is
                                            really in the cart, with $quantityError explaining.
                                            It snaps to that return value, not to a re-rendered
                                            data-qty: Livewire resolves the call before it morphs
                                            the new HTML in, so data-qty would still be the old
                                            number and a 1 -> 2 save would snap back to 1.
                                        --}}
                                        <div class="flex items-center justify-between sm:flex-col sm:items-end sm:justify-between gap-3 shrink-0"
                                             data-max="{{ $availableStock }}"
                                             x-data="{
                                                 qty: {{ $item->quantity }},
                                                 saved: {{ $item->quantity }},
                                                 price: @js($item->price !== null ? (float) $item->price : null),
                                                 locked: @js($isUnavailable && ! $isOverStock),
                                                 timer: null,
                                                 saving: false,
                                                 again: false,
                                                 queued: false,
                                                 get max() { return Math.max(1, parseInt(this.$root.dataset.max, 10) || 0); },
                                                 get current() { return parseInt(this.qty, 10) || 1; },
                                                 clamp(n) { return Math.min(Math.max(1, n), this.max); },
                                                 step(by) {
                                                     if (this.locked) return;
                                                     // For an over-stock row, one press drops straight to
                                                     // what is actually left rather than stepping down one
                                                     // at a time into repeated 'only N available' errors.
                                                     this.qty = by < 0 && this.current > this.max
                                                         ? this.max
                                                         : this.clamp(this.current + by);
                                                     this.queue(350);
                                                 },
                                                 typed(el) {
                                                     const digits = el.value.replace(/\D/g, '');
                                                     el.value = digits;
                                                     this.qty = digits;
                                                 },
                                                 commit() {
                                                     this.qty = this.clamp(this.current);
                                                     this.queue(0);
                                                 },
                                                 queue(delay) {
                                                     clearTimeout(this.timer);
                                                     if (! this.queued) { this.queued = true; this.pendingSaves++; }
                                                     this.timer = setTimeout(() => this.save(), delay);
                                                 },
                                                 async save() {
                                                     // One request at a time per row; a change made while
                                                     // one is in flight is sent when it returns.
                                                     if (this.saving) { this.again = true; return; }
                                                     if (this.current !== this.saved) {
                                                         this.saving = true;
                                                         // A failed request falls through to the snap-back
                                                         // below instead of leaving checkout held forever.
                                                         try {
                                                             const saved = await $wire.updateQuantity({{ $item->id }}, this.current);
                                                             if (Number.isInteger(saved)) this.saved = saved;
                                                         } catch (e) {}
                                                         this.saving = false;
                                                         if (this.again) { this.again = false; return this.save(); }
                                                     }
                                                     this.qty = this.saved;
                                                     this.queued = false;
                                                     this.pendingSaves--;
                                                 },
                                             }">

                                            {{-- Quantity Stepper --}}
                                            <div class="flex items-center overflow-hidden rounded-full border border-gray-300 bg-white">
                                                <button
                                                    type="button"
                                                    @click="step(-1)"
                                                    :disabled="locked || current <= 1"
                                                    @disabled($item->quantity <= 1 || ($isUnavailable && !$isOverStock))
                                                    class="flex size-11 items-center justify-center text-lg font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primary)] disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-white"
                                                    aria-label="Decrease quantity of {{ $item->product->name }}">
                                                    &minus;
                                                </button>

                                                {{-- type="text", not "number": a number input changes
                                                     value on mouse-wheel scroll and shows spinner arrows. --}}
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    autocomplete="off"
                                                    value="{{ $item->quantity }}"
                                                    :value="qty"
                                                    @input="typed($event.target)"
                                                    @blur="commit()"
                                                    @keydown.enter.prevent="$event.target.blur()"
                                                    :readonly="locked"
                                                    @readonly($isUnavailable && !$isOverStock)
                                                    class="h-11 w-12 border-none bg-transparent p-0 text-center text-sm font-semibold text-gray-900 focus:ring-0 read-only:text-gray-400"
                                                    aria-label="Quantity of {{ $item->product->name }}">

                                                <button
                                                    type="button"
                                                    @click="step(1)"
                                                    :disabled="locked || current >= max"
                                                    @disabled($item->quantity >= $availableStock || $isUnavailable)
                                                    class="flex size-11 items-center justify-center text-lg font-semibold text-[var(--color-primary)] transition hover:bg-[color-mix(in_srgb,var(--color-primary)_9%,white)] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primary)] disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-white"
                                                    aria-label="Increase quantity of {{ $item->product->name }}">
                                                    +
                                                </button>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                {{-- Item Subtotal. Previewed from the unit price the
                                                     server rendered (CartItem::price) so it moves with the
                                                     stepper; the re-render after the save replaces it. --}}
                                                <p class="font-bold text-gray-900">
                                                    @if($item->price !== null)
                                                        <span x-text="'₱' + (price * current).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">₱{{ number_format($item->subtotal, 2) }}</span>
                                                    @else
                                                        &mdash;
                                                    @endif
                                                </p>

                                                {{-- Remove Button --}}
                                                <button
                                                    type="button"
                                                    wire:click="removeItem({{ $item->id }})"
                                                    class="flex size-11 -mr-3 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-600 {{ $focusRing }}"
                                                    title="Remove item"
                                                    aria-label="Remove {{ $item->product->name }} from cart">
                                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round"
                                                              stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            @endforeach
                        </div>
                    </div>

                    {{-- Continue Shopping --}}
                    <a href="{{ route('products.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-full border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 {{ $focusRing }}">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Continue shopping
                    </a>

                </div>


                {{-- Order Summary --}}
                <aside class="lg:col-span-4 lg:sticky lg:top-24">

                    <div class="{{ $card }} p-6">

                        <h2 class="text-base font-bold text-gray-900 mb-5">
                            Order Summary
                        </h2>

                        <div class="space-y-3 text-sm transition-opacity" :class="pendingSaves > 0 && 'opacity-50'">

                            <div class="flex justify-between">

                                {{--
                                    This counts only what is being priced, so
                                    it always matches the amount beside it.
                                --}}
                                <span class="text-gray-500">
                                    Subtotal ({{ $this->payableQuantity }} items)
                                </span>

                                <span class="font-medium text-gray-900">
                                    ₱{{ number_format($this->subtotal, 2) }}
                                </span>

                            </div>

                            {{--
                                Say what was left out. Without this the total
                                just looks too low for the rows on screen.
                            --}}
                            @if($this->excludedItemCount > 0)

                                <p class="text-xs text-gray-500">
                                    {{ $this->excludedItemCount }} unavailable {{ $this->excludedItemCount === 1 ? 'item' : 'items' }} not included.
                                </p>

                            @endif

                        </div>

                        {{-- Total --}}
                        <div class="border-t border-gray-100 mt-5 pt-5 mb-5 transition-opacity" :class="pendingSaves > 0 && 'opacity-50'">

                            <div class="flex justify-between items-baseline">

                                <span class="font-semibold text-gray-900">
                                    Total
                                </span>

                                <span class="text-2xl font-extrabold tracking-[-0.02em] text-[var(--color-primary)]">
                                    ₱{{ number_format($this->subtotal, 2) }}
                                </span>

                            </div>

                        </div>

                        {{-- Checkout --}}
                        @auth('customer')

                            @if($this->hasUnavailableItems)

                                {{-- Checkout Disabled --}}
                                <button
                                    type="button"
                                    disabled
                                    class="flex min-h-11 w-full items-center justify-center rounded-full bg-gray-200 px-6 text-sm font-semibold text-gray-500 cursor-not-allowed">
                                    Resolve Cart Items to Checkout
                                </button>

                                <p class="text-xs text-red-600 text-center mt-3">
                                    Lower quantities that exceed available stock, or remove unavailable products before checkout.
                                </p>

                            @else

                                {{-- Checkout Enabled --}}
                                <a href="{{ route('checkout') }}"
                                   @click="pendingSaves > 0 && $event.preventDefault()"
                                   :aria-disabled="pendingSaves > 0"
                                   :class="pendingSaves > 0 && 'opacity-60 cursor-wait'"
                                   class="flex min-h-11 w-full items-center justify-center rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $focusRing }}">
                                    <span x-text="pendingSaves > 0 ? 'Updating cart…' : 'Proceed to Checkout'">Proceed to Checkout</span>
                                </a>

                            @endif

                        @else

                            {{-- Login to Checkout --}}
                            <a href="{{ route('login') }}"
                               class="flex min-h-11 w-full items-center justify-center rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $focusRing }}">
                                Login to Checkout
                            </a>

                            <p class="text-xs text-gray-500 text-center mt-3">
                                Or
                                <a href="{{ route('register') }}"
                                   class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">
                                    create an account
                                </a>
                            </p>

                        @endauth

                    </div>

                </aside>

            </div>

        @else

            {{-- Empty Cart --}}
            <div class="{{ $card }} px-6 py-16 text-center">

                <div class="mx-auto mb-5 w-14 h-14 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="1.8"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>

                <h2 class="text-base font-semibold text-gray-900 mb-1">
                    Your cart is empty
                </h2>

                <p class="text-sm text-gray-600 mb-6">
                    Add some products to get started!
                </p>

                <a href="{{ route('products.index') }}"
                   class="inline-flex min-h-11 items-center rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $focusRing }}">
                    Start Shopping
                </a>

            </div>

        @endif

    </div>
</div>
