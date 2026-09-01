{{--
    The Add to Cart popup lives in the front-end layout (<x-cart-toast />)
    so that only one popup is shown per page instead of one per card.
--}}
<div class="group relative bg-white rounded-lg shadow-sm hover:shadow-lg transition duration-300 overflow-hidden">
    <a href="{{ route('products.show', $product->slug) }}" class="block">

        <!-- Product Image -->
        <div class="aspect-square overflow-hidden bg-gray-200">
            @if($product->primaryImage)
                <img src="{{ $product->primaryImage->url }}"
                     alt="{{ $product->name }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
            @else
                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-300 to-gray-400">
                    <span class="text-6xl text-gray-500">
                        {{ substr($product->name, 0, 1) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Badges -->
        <div class="absolute top-2 left-2 flex flex-col gap-2">

            @if($product->is_featured)
                <span class="bg-amber-500 text-white text-xs font-semibold px-2 py-1 rounded">
                    Featured
                </span>
            @endif

            @if($product->discount_percentage > 0)
                <span class="bg-red-500 text-white text-xs font-semibold px-2 py-1 rounded">
                    -{{ $product->discount_percentage }}%
                </span>
            @endif

            @if($product->stock_status === 'out_of_stock')
                <span class="bg-gray-800 text-white text-xs font-semibold px-2 py-1 rounded">
                    Out of Stock
                </span>
            @endif

        </div>

        <!-- Product Info -->
        <div class="p-4">

            <!-- Category -->
            <p class="text-xs text-gray-500 mb-1">
                {{ $product->category->name }}
            </p>

            <!-- Product Name -->
            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 transition"
                onmouseover="this.style.color='#1E6031'"
                onmouseout="this.style.color=''">
                {{ $product->name }}
            </h3>

            <!-- Rating -->
            @if($product->reviews_count > 0)
                <div class="flex items-center gap-1 mb-2">

                    <div class="flex text-amber-400">
                        @for($i = 1; $i <= 5; $i)

                            @if($i <= floor($product->average_rating))
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4 fill-current text-gray-300" viewBox="0 0 20 20">
                                    <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                </svg>
                            @endif

                        @endfor
                    </div>

                    <span class="text-xs text-gray-600">
                        ({{ $product->reviews_count }})
                    </span>

                </div>
            @endif

            <!-- Price -->
            <div class="flex items-center gap-2">

                <span class="text-xl font-bold text-gray-900">
                    ${{ number_format($product->price, 2) }}
                </span>

                @if($product->compare_price)
                    <span class="text-sm text-gray-500 line-through">
                        ${{ number_format($product->compare_price, 2) }}
                    </span>
                @endif

            </div>

        </div>
    </a>


    <!-- Add to Cart Button -->
    @if($product->stock_status === 'in_stock')

        @if($product->has_variants)

            {{--
                Products sold by variant need a variant chosen before they
                can be added, and the picker only exists on the product
                page. Send the customer there instead.
            --}}
            <div class="p-4 pt-0">

                <a href="{{ route('products.show', $product->slug) }}"
                   wire:navigate
                   style="background-color: #1E6031;"
                   class="block w-full text-center text-white py-2 px-4 rounded-lg hover:opacity-90 transition font-medium">
                    Add To Cart
                </a>

            </div>

        @else

            <div class="p-4 pt-0">

                <button
                    wire:click="addToCart"
                    wire:loading.attr="disabled"
                    wire:target="addToCart"
                    style="background-color: #1E6031;"
                    class="w-full cursor-pointer text-white py-2 px-4 rounded-lg hover:opacity-90 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >

                    <!-- Normal Button Text -->
                    <span wire:loading.remove wire:target="addToCart">
                        Add to Cart
                    </span>

                    <!-- Display while Laravel is adding the item -->
                    <span wire:loading wire:target="addToCart">
                        Adding...
                    </span>

                </button>

            </div>

        @endif

    @else

        <div class="p-4 pt-0">

            <button
                disabled
                class="w-full bg-gray-300 text-gray-500 py-2 px-4 rounded-lg cursor-not-allowed font-medium"
            >
                Out of Stock
            </button>

        </div>

    @endif

</div>