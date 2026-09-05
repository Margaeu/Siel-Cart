<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <ol class="flex items-center gap-2">
                <li>
                    <a href="{{ route('home') }}"
                       class="text-gray-500 hover:text-[var(--color-primary)]">
                        Home
                    </a>
                </li>
                <li class="text-gray-400">/</li>
                <li class="text-gray-900 font-medium">
                    Shopping Cart
                </li>
            </ol>
        </nav>

        <!-- Header -->
        <h1 class="text-3xl font-bold text-gray-900 mb-8">
            Shopping Cart
        </h1>

        @if($cart && $cart->items->count() > 0)

            <div class="lg:grid lg:grid-cols-3 lg:gap-8">

                <!-- Cart Items -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Flash Success Message -->
                    @if (session()->has('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Quantity Error Message -->
                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($quantityError)
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {{ $quantityError }}
                        </div>
                    @endif

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
                        @endphp

                        <!-- Cart Item -->
                        {{--
                            wire:key lets Livewire track each row so that
                            removing one does not leave the next row showing
                            the removed row's values.
                        --}}
                        {{--
                            Only a row the customer cannot fix is dimmed. An
                            over-stock row stays fully legible because they are
                            expected to act on it.
                        --}}
                        <div wire:key="cart-item-{{ $item->id }}"
                             class="bg-white rounded-lg shadow-sm p-6
                            {{ $isUnavailable && !$isOverStock ? 'opacity-50 bg-gray-100' : '' }}">

                            <div class="flex gap-4">

                                <!-- Product Image -->
                                <div class="flex-shrink-0">
                                    <div class="w-24 h-24 rounded-lg overflow-hidden bg-gray-100">

                                    @php
                                        // Use the selected variant's first image.
                                        // Fall back to the product's general primary image.
                                        $cartImage = $item->variant?->images->first()
                                            ?? $item->product->primaryImage;
                                    @endphp

                                    @if($cartImage)
                                        <img
                                            src="{{ $cartImage->url }}"
                                            alt="{{ $item->product->name }}{{ $item->variant ? ' - '.$item->variant->name : '' }}"
                                            class="w-full h-full object-cover
                                                {{ $isUnavailable && !$isOverStock ? 'grayscale' : '' }}">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-300 to-gray-400">
                                            <span class="text-2xl text-gray-500">
                                                {{ substr($item->product->name, 0, 1) }}
                                            </span>
                                        </div>
                                    @endif

                                    </div>
                                </div>

                                <!-- Product Info -->
                                <div class="flex-1">

                                    <h3 class="font-semibold text-gray-900 mb-1">
                                        {{ $item->product->name }}
                                    </h3>

                                    @if($item->variant)
                                        <p class="text-sm text-gray-600 mb-2">
                                            {{ $item->variant->name }}
                                        </p>
                                    @endif

                                    {{--
                                        $item->price is the variant price when the
                                        item has a variant, and the product price
                                        when it does not.
                                    --}}
                                    <p class="text-lg font-bold text-[var(--color-primary)]">
                                        @if($item->price !== null)
                                            ₱{{ number_format($item->price, 2) }}
                                        @else
                                            Price unavailable
                                        @endif
                                    </p>

                                    <!-- Availability Notice -->
                                    @if($isOverStock)

                                        {{-- Fixable: tell them what is left. --}}
                                        <p class="text-sm font-semibold text-amber-600 mt-2">
                                            Only {{ $availableStock }} left in stock.
                                            Lower the quantity to continue.
                                        </p>

                                    @elseif($isUnavailable)

                                        <p class="text-sm font-semibold text-red-600 mt-2">
                                            This product is currently unavailable.
                                        </p>

                                    @endif

                                </div>

                                <!-- Quantity & Actions -->
                                <div class="flex flex-col items-end justify-between">

                                    <!-- Remove Button -->
                                    <button
                                        wire:click="removeItem({{ $item->id }})"
                                        class="text-red-600 hover:text-red-700"
                                        title="Remove item">

                                        <svg class="w-5 h-5"
                                             fill="none"
                                             stroke="currentColor"
                                             viewBox="0 0 24 24">

                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />

                                        </svg>
                                    </button>

                                    <!-- Quantity Stepper -->
                                    <div class="flex items-center overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm">
                                        {{--
                                            For an over-stock row, one press drops
                                            straight to what is actually left rather
                                            than stepping down one at a time into
                                            repeated "only N available" errors.
                                        --}}
                                        <button
                                            type="button"
                                            wire:click="updateQuantity({{ $item->id }}, {{ min($item->quantity - 1, $availableStock) }})"
                                            wire:loading.attr="disabled"
                                            wire:target="updateQuantity"
                                            @disabled($item->quantity <= 1 || ($isUnavailable && !$isOverStock))
                                            class="flex h-10 w-10 items-center justify-center text-xl font-semibold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primary)] disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-white"
                                            aria-label="Decrease quantity of {{ $item->product->name }}">
                                            &minus;
                                        </button>

                                        <span
                                            wire:key="cart-item-qty-{{ $item->id }}-{{ $item->quantity }}"
                                            class="flex h-10 min-w-12 items-center justify-center border-x border-gray-200 px-3 text-center font-semibold text-gray-900"
                                            aria-live="polite"
                                            aria-label="Quantity">
                                            {{ $item->quantity }}
                                        </span>

                                        <button
                                            type="button"
                                            wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                            wire:loading.attr="disabled"
                                            wire:target="updateQuantity"
                                            @disabled($item->quantity >= $availableStock || $isUnavailable)
                                            class="flex h-10 w-10 items-center justify-center text-xl font-semibold text-[var(--color-primary)] transition hover:bg-[#f2f7f4] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primary)] disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-white"
                                            aria-label="Increase quantity of {{ $item->product->name }}">
                                            +
                                        </button>
                                    </div>

                                    <!-- Item Subtotal -->
                                    <p class="text-lg font-bold text-gray-900">
                                        @if($item->price !== null)
                                            ₱{{ number_format($item->subtotal, 2) }}
                                        @else
                                            &mdash;
                                        @endif
                                    </p>

                                </div>

                            </div>

                        </div>

                    @endforeach

                    <!-- Clear Cart / Continue Shopping -->
                    <div class="flex justify-between items-center pt-4">

                        <button
                            wire:click="clearCart"
                            wire:confirm="Are you sure you want to clear the cart?"
                            class="text-red-600 hover:text-red-700 font-medium">
                            Clear Cart
                        </button>

                        <a href="{{ route('products.index') }}"
                           class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] font-medium">
                            ← Continue Shopping
                        </a>

                    </div>

                </div>


                <!-- Order Summary -->
                <div class="mt-8 lg:mt-0">

                    <div class="bg-white rounded-lg shadow-sm p-6 sticky top-24">

                        <h2 class="text-xl font-bold text-gray-900 mb-6">
                            Order Summary
                        </h2>

                        <div class="space-y-3 mb-6">

                            <div class="flex justify-between">

                                {{--
                                    This counts only what is being priced, so
                                    it always matches the amount beside it.
                                --}}
                                <span class="text-gray-600">
                                    Subtotal ({{ $this->payableQuantity }} items)
                                </span>

                                <span class="font-medium">
                                    ₱{{ number_format($this->subtotal, 2) }}
                                </span>

                            </div>

                            {{--
                                Say what was left out. Without this the total
                                just looks too low for the rows on screen.
                            --}}
                            @if($this->excludedItemCount > 0)

                                <p class="text-sm text-gray-500">
                                    {{ $this->excludedItemCount }} unavailable {{ $this->excludedItemCount === 1 ? 'item' : 'items' }} not included.
                                </p>

                            @endif

                        </div>

                        <!-- Total -->
                        <div class="border-t pt-4 mb-6">

                            <div class="flex justify-between items-center">

                                <span class="text-lg font-semibold">
                                    Total
                                </span>

                                <span class="text-2xl font-bold text-[var(--color-primary)]">
                                    ₱{{ number_format($this->subtotal, 2) }}
                                </span>

                            </div>

                        </div>


                        <!-- Checkout -->
                        @auth('customer')

                            @if($this->hasUnavailableItems)

                                <!-- Checkout Disabled -->
                                <button
                                    disabled
                                    class="block w-full bg-gray-300 text-gray-500 text-center py-3 px-6 rounded-lg cursor-not-allowed font-semibold">
                                    Resolve Cart Items to Checkout
                                </button>

                                <p class="text-sm text-red-600 text-center mt-3">
                                    Lower quantities that exceed available stock, or remove unavailable products before checkout.
                                </p>

                            @else

                                <!-- Checkout Enabled -->
                                <a href="{{ route('checkout') }}"
                                   class="block w-full bg-[var(--color-primary)] text-white text-center py-3 px-6 rounded-lg hover:bg-[var(--color-primary-hover)] transition font-semibold">
                                    Proceed to Checkout
                                </a>

                            @endif

                        @else

                            <!-- Login to Checkout -->
                            <a href="{{ route('login') }}"
                               class="block w-full bg-[var(--color-primary)] text-white text-center py-3 px-6 rounded-lg hover:bg-[var(--color-primary-hover)] transition font-semibold">
                                Login to Checkout
                            </a>

                            <p class="text-sm text-gray-600 text-center mt-3">
                                Or
                                <a href="{{ route('register') }}"
                                   class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">
                                    create an account
                                </a>
                            </p>

                        @endauth

                    </div>

                </div>

            </div>

        @else

            <!-- Empty Cart -->
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">

                <svg class="mx-auto w-24 h-24 text-gray-400 mb-4"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">

                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />

                </svg>

                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    Your cart is empty
                </h2>

                <p class="text-gray-600 mb-6">
                    Add some products to get started!
                </p>

                <a href="{{ route('products.index') }}"
                   class="inline-block bg-[var(--color-primary)] text-white px-8 py-3 rounded-lg hover:bg-[var(--color-primary-hover)] transition font-semibold">
                    Start Shopping
                </a>

            </div>

        @endif

    </div>
</div>