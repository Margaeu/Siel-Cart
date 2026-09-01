<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <ol class="flex items-center gap-2">
                <li>
                    <a href="{{ route('home') }}"
                       class="text-gray-500 hover:text-[#1E6031]">
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
                    @if($quantityError)
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {{ $quantityError }}
                        </div>
                    @endif

                    @foreach($cart->items as $item)

                        @php
                            /*
                             * Get the latest stock information.
                             *
                             * If the item has a variant, use the variant stock.
                             * Otherwise, use the main product stock.
                             */
                            $availableStock = $item->variant
                                ? $item->variant->stock_quantity
                                : $item->product->stock_quantity;

                            /*
                             * Determine whether this cart item is unavailable.
                             *
                             * An item is unavailable when:
                             * - Its stock is zero or below
                             * - Its current quantity is greater than available stock
                             * - The product itself is marked as out of stock
                             */
                            $isUnavailable =
                                $availableStock <= 0 ||
                                $item->quantity > $availableStock ||
                                $item->product->stock_status === 'out_of_stock';
                        @endphp

                        <!-- Cart Item -->
                        <div class="bg-white rounded-lg shadow-sm p-6
                            {{ $isUnavailable ? 'opacity-50 bg-gray-100' : '' }}">

                            <div class="flex gap-4">

                                <!-- Product Image -->
                                <div class="flex-shrink-0">
                                    <div class="w-24 h-24 rounded-lg overflow-hidden bg-gray-100">

                                        @if($item->product->primaryImage)

                                            <img src="{{ $item->product->primaryImage->url }}"
                                                 alt="{{ $item->product->name }}"
                                                 class="w-full h-full object-cover
                                                 {{ $isUnavailable ? 'grayscale' : '' }}">

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

                                    <p class="text-lg font-bold text-[#1E6031]">
                                        ${{ number_format($item->product->price, 2) }}
                                    </p>

                                    <!-- Out of Stock Notice -->
                                    @if($isUnavailable)
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

                                    <!-- Direct Quantity Input -->
                                    <div class="flex items-center gap-2">

                                        <input
                                            type="number"
                                            min="1"
                                            max="{{ max(1, $availableStock) }}"
                                            value="{{ $item->quantity }}"
                                            wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                            class="w-16 h-8 text-center font-medium border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-[#1E6031] focus:border-[#1E6031]"
                                            aria-label="Quantity">

                                    </div>

                                    <!-- Item Subtotal -->
                                    <p class="text-lg font-bold text-gray-900">
                                        ${{ number_format($item->product->price * $item->quantity, 2) }}
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
                           class="text-[#1E6031] hover:text-[#164824] font-medium">
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

                                <span class="text-gray-600">
                                    Subtotal ({{ $cart->total_quantity }} items)
                                </span>

                                <span class="font-medium">
                                    ${{ number_format($this->subtotal, 2) }}
                                </span>

                            </div>

                            <div class="flex justify-between">

                                <span class="text-gray-600">
                                    Shipping
                                </span>

                                <span class="font-medium">
                                    Calculated at checkout
                                </span>

                            </div>

                        </div>

                        <!-- Total -->
                        <div class="border-t pt-4 mb-6">

                            <div class="flex justify-between items-center">

                                <span class="text-lg font-semibold">
                                    Total
                                </span>

                                <span class="text-2xl font-bold text-[#1E6031]">
                                    ${{ number_format($this->subtotal, 2) }}
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
                                    Remove Unavailable Items to Checkout
                                </button>

                                <p class="text-sm text-red-600 text-center mt-3">
                                    Please remove unavailable products from your cart before checkout.
                                </p>

                            @else

                                <!-- Checkout Enabled -->
                                <a href="{{ route('checkout') }}"
                                   class="block w-full bg-[#1E6031] text-white text-center py-3 px-6 rounded-lg hover:bg-[#164824] transition font-semibold">
                                    Proceed to Checkout
                                </a>

                            @endif

                        @else

                            <!-- Login to Checkout -->
                            <a href="{{ route('login') }}"
                               class="block w-full bg-[#1E6031] text-white text-center py-3 px-6 rounded-lg hover:bg-[#164824] transition font-semibold">
                                Login to Checkout
                            </a>

                            <p class="text-sm text-gray-600 text-center mt-3">
                                Or
                                <a href="{{ route('register') }}"
                                   class="text-[#1E6031] hover:text-[#164824]">
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
                   class="inline-block bg-[#1E6031] text-white px-8 py-3 rounded-lg hover:bg-[#164824] transition font-semibold">
                    Start Shopping
                </a>

            </div>

        @endif

    </div>
</div>