<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div class="flex items-center {{ $step >= 1 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $step >= 1 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }}">
                            @if($step > 1)
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                <span class="font-semibold">1</span>
                            @endif
                        </div>
                        <span class="ml-2 font-medium">Shipping</span>
                    </div>
                    <div class="w-24 h-1 mx-4 {{ $step >= 2 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                    <div class="flex items-center {{ $step >= 2 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $step >= 2 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }}">
                            @if($step > 2)
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                <span class="font-semibold">2</span>
                            @endif
                        </div>
                        <span class="ml-2 font-medium">Review</span>
                    </div>
                    <div class="w-24 h-1 mx-4 {{ $step >= 3 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                    <div class="flex items-center {{ $step >= 3 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $step >= 3 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }}">
                            <span class="font-semibold">3</span>
                        </div>
                        <span class="ml-2 font-medium">Payment</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:grid lg:grid-cols-3 lg:gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                @if (session()->has('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Step 1: Shipping Address -->
                @if($step === 1)
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Shipping Address</h2>

                        <!-- Use Existing Address -->
                        @if($addresses->count() > 0)
                            <div class="mb-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" 
                                           wire:model.live="useExistingAddress"
                                           class="w-4 h-4 text-blue-600 rounded">
                                    <span class="font-medium">Use saved address</span>
                                </label>
                            </div>

                            @if($useExistingAddress)
                                <div class="grid gap-4 mb-6">
                                    @foreach($addresses as $address)
                                        <label class="relative cursor-pointer">
                                            <input type="radio" 
                                                   wire:model="selectedAddressId"
                                                   value="{{ $address->id }}"
                                                   class="peer sr-only">
                                            <div class="border-2 rounded-lg p-4 peer-checked:border-blue-600 peer-checked:bg-indigo-50 hover:border-indigo-400 transition">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <p class="font-semibold text-gray-900">{{ $address->full_name }}</p>
                                                        <p class="text-gray-600">{{ $address->phone }}</p>
                                                        <p class="text-gray-600 mt-2">{{ $address->full_address }}</p>
                                                        @if($address->is_default)
                                                            <span class="inline-block mt-2 bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded">
                                                                Default
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        <!-- New Address Form -->
                        @if(!$useExistingAddress || $addresses->count() === 0)
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                    <input type="text" 
                                           wire:model="full_name"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    @error('full_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                                    <input type="tel" 
                                           wire:model="phone"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 1 *</label>
                                    <input type="text" 
                                           wire:model="address_line_1"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    @error('address_line_1') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                                    <input type="text" 
                                           wire:model="address_line_2"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                                        <input type="text" 
                                               wire:model="city"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('city') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                        <input type="text" 
                                               wire:model="state"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code *</label>
                                        <input type="text" 
                                               wire:model="postal_code"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('postal_code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                                        <select wire:model="country"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <option value="US">United States</option>
                                            <option value="CA">Canada</option>
                                            <option value="UK">United Kingdom</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-between mt-6 pt-6 border-t">
                            <a href="{{ route('cart.index') }}" 
                               class="text-gray-600 hover:text-gray-900 font-medium">
                                ← Back to Cart
                            </a>
                            <button wire:click="nextStep"
                                    class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                                Continue to Review
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Step 2: Review Order -->
                @if($step === 2)
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Review Your Order</h2>

                        <!-- Order Items -->
                        <div class="space-y-4 mb-6">
                            @foreach($cart as $item)
                                <div class="flex gap-4 pb-4 border-b">
                                    <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                        @if($item['image'])
                                            <img src="{{ asset('storage/' . $item['image']) }}" 
                                                 alt="{{ $item['name'] }}"
                                                 class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900">{{ $item['name'] }}</h3>
                                        @if($item['variant_name'])
                                            <p class="text-sm text-gray-600">{{ $item['variant_name'] }}</p>
                                        @endif
                                        <p class="text-sm text-gray-600">Quantity: {{ $item['quantity'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-900">${{ number_format($item['price'] * $item['quantity'], 2) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Customer Notes -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Notes (Optional)</label>
                            <textarea wire:model="customerNotes"
                                      rows="3"
                                      placeholder="Special instructions for your order..."
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>

                        <div class="flex justify-between pt-6 border-t">
                            <button wire:click="previousStep"
                                    class="text-gray-600 hover:text-gray-900 font-medium">
                                ← Back to Shipping
                            </button>
                            <button wire:click="nextStep"
                                    class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                                Continue to Payment
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Step 3: Payment -->
                @if($step === 3)
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Payment Method</h2>

                        <div class="space-y-4 mb-6">
                            <label class="relative cursor-pointer">
                                <input type="radio" 
                                       wire:model="paymentMethod"
                                       value="stripe"
                                       class="peer sr-only">
                                <div class="border-2 rounded-lg p-4 peer-checked:border-blue-600 peer-checked:bg-indigo-50 hover:border-indigo-400 transition">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold text-gray-900">Credit/Debit Card</p>
                                                <p class="text-sm text-gray-600">Pay securely with Stripe</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <label class="relative cursor-pointer">
                                <input type="radio" 
                                       wire:model="paymentMethod"
                                       value="cash_on_delivery"
                                       class="peer sr-only">
                                <div class="border-2 rounded-lg p-4 peer-checked:border-blue-600 peer-checked:bg-indigo-50 hover:border-indigo-400 transition">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <div>
                                                <p class="font-semibold text-gray-900">Cash on Delivery</p>
                                                <p class="text-sm text-gray-600">Pay when you receive your order</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div class="flex justify-between pt-6 border-t">
                            <button wire:click="previousStep"
                                    class="text-gray-600 hover:text-gray-900 font-medium">
                                ← Back to Review
                            </button>
                            <button wire:click="placeOrder"
                                    class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                                Place Order
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Order Summary -->
            <div>
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">${{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium">
                                @if($shippingCost > 0)
                                    ${{ number_format($shippingCost, 2) }}
                                @else
                                    <span class="text-green-600">FREE</span>
                                @endif
                            </span>
                        </div>
                        @if($discountAmount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Discount</span>
                                <span class="font-medium">-${{ number_format($discountAmount, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Coupon Code -->
                    @if(!$appliedCoupon)
                        <div class="mb-6 pb-6 border-b">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Coupon Code</label>
                            <div class="flex gap-2">
                                <input type="text" 
                                       wire:model="couponCode"
                                       placeholder="Enter code"
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                <button wire:click="applyCoupon"
                                        class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 transition">
                                    Apply
                                </button>
                            </div>
                            @if (session()->has('coupon_error'))
                                <p class="text-red-600 text-sm mt-2">{{ session('coupon_error') }}</p>
                            @endif
                            @if (session()->has('coupon_success'))
                                <p class="text-green-600 text-sm mt-2">{{ session('coupon_success') }}</p>
                            @endif
                        </div>
                    @else
                        <div class="mb-6 pb-6 border-b">
                            <div class="flex items-center justify-between bg-green-50 p-3 rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-green-800">Coupon Applied</p>
                                    <p class="text-xs text-green-600">{{ $appliedCoupon->code }}</p>
                                </div>
                                <button wire:click="removeCoupon"
                                        class="text-red-600 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold">Total</span>
                            <span class="text-2xl font-bold text-blue-600">
                                ${{ number_format($total, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>