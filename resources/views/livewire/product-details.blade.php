<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <ol class="flex items-center gap-2">
                <li><a href="{{ route('home') }}" class="text-gray-500 hover:text-blue-600">Home</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="{{ route('products.index') }}" class="text-gray-500 hover:text-blue-600">Shop</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="{{ route('products.index', ['category' => $product->category->slug]) }}" 
                       class="text-gray-500 hover:text-blue-600">{{ $product->category->name }}</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-gray-900 font-medium">{{ $product->name }}</li>
            </ol>
        </nav>

        <!-- Product Detail -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
            <div class="lg:grid lg:grid-cols-2 lg:gap-8 p-8">
                <!-- Images -->
                <div>
                    <!-- Main Image -->
                    <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-4">
                        @if($selectedImage)
                            <img src="{{ asset('storage/' . $selectedImage) }}" 
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-300 to-gray-400">
                                <span class="text-9xl text-gray-500">{{ substr($product->name, 0, 1) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Thumbnail Images -->
                    @if($product->images->count() > 1)
                        <div class="grid grid-cols-4 gap-4">
                            @foreach($product->images as $image)
                                <button wire:click="selectImage('{{ $image->image_path }}')"
                                        class="aspect-square rounded-lg overflow-hidden border-2 {{ $selectedImage === $image->image_path ? 'border-blue-600' : 'border-gray-200' }} hover:border-indigo-400 transition">
                                    <img src="{{ asset('storage/' . $image->image_path) }}" 
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Product Info -->
                <div>
                    <!-- Badges -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($product->is_featured)
                            <span class="bg-yellow-100 text-yellow-800 text-sm font-semibold px-3 py-1 rounded">
                                Featured
                            </span>
                        @endif
                        @if($product->stock_status === 'in_stock')
                            <span class="bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 rounded">
                                In Stock
                            </span>
                        @else
                            <span class="bg-red-100 text-red-800 text-sm font-semibold px-3 py-1 rounded">
                                Out of Stock
                            </span>
                        @endif
                    </div>

                    <!-- Brand -->
                    @if($product->brand)
                        <p class="text-sm text-gray-500 mb-2">{{ $product->brand->name }}</p>
                    @endif

                    <!-- Title -->
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $product->name }}</h1>

                    <!-- Rating -->
                    @if($product->reviews_count > 0)
                        <div class="flex items-center gap-2 mb-4">
                            <div class="flex text-yellow-400">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($product->average_rating))
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 fill-current text-gray-300" viewBox="0 0 20 20">
                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                        </svg>
                                    @endif
                                @endfor
                            </div>
                            <span class="text-gray-600">{{ number_format($product->average_rating, 1) }} ({{ $product->reviews_count }} reviews)</span>
                        </div>
                    @endif

                    <!-- Price -->
                    <div class="mb-6">
                        @if($selectedVariant)
                            @php
                                $variant = $product->variants->find($selectedVariant);
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="text-3xl font-bold text-gray-900">${{ number_format($variant->price, 2) }}</span>
                                @if($variant->compare_price)
                                    <span class="text-xl text-gray-500 line-through">${{ number_format($variant->compare_price, 2) }}</span>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm font-semibold">
                                        -{{ $variant->discount_percentage }}%
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center gap-3">
                                <span class="text-3xl font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
                                @if($product->compare_price)
                                    <span class="text-xl text-gray-500 line-through">${{ number_format($product->compare_price, 2) }}</span>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm font-semibold">
                                        -{{ $product->discount_percentage }}%
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Short Description -->
                    @if($product->short_description)
                        <p class="text-gray-600 mb-6">{{ $product->short_description }}</p>
                    @endif

                    <!-- Variants -->
                    @if($product->has_variants && $product->variants->isNotEmpty())
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-900 mb-3">Select Variant:</label>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($product->variants->where('is_active', true) as $variant)
                                    <button wire:click="selectVariant({{ $variant->id }})"
                                            class="border-2 rounded-lg p-3 text-left transition {{ $selectedVariant === $variant->id ? 'border-blue-600 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400' }}">
                                        <p class="font-medium text-gray-900">{{ $variant->name }}</p>
                                        <p class="text-sm text-gray-600">${{ number_format($variant->price, 2) }}</p>
                                        <p class="text-xs {{ $variant->stock_status === 'in_stock' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $variant->stock_status === 'in_stock' ? 'In Stock' : 'Out of Stock' }}
                                        </p>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Quantity -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-900 mb-3">Quantity:</label>
                        <div class="flex items-center gap-3">
                            <button wire:click="decrementQuantity"
                                    class="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                </svg>
                            </button>
                            <input type="number" 
                                   wire:model="quantity"
                                   min="1"
                                   class="w-20 text-center border border-gray-300 rounded-lg py-2">
                            <button wire:click="incrementQuantity"
                                    class="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Flash Messages -->
                    @if (session()->has('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session()->has('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Add to Cart -->
                    @if($product->stock_status === 'in_stock')
                        <button wire:click="addToCart"
                                class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-indigo-700 transition font-semibold text-lg">
                            Add to Cart
                        </button>
                    @else
                        <button disabled
                                class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg cursor-not-allowed font-semibold text-lg">
                            Out of Stock
                        </button>
                    @endif

                    <!-- Product Details -->
                    <div class="mt-8 border-t pt-6 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">SKU:</span>
                            <span class="font-medium">{{ $product->sku }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Category:</span>
                            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" 
                               class="font-medium text-blue-600 hover:text-indigo-700">
                                {{ $product->category->name }}
                            </a>
                        </div>
                        @if($product->brand)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Brand:</span>
                                <a href="{{ route('products.index', ['brand' => $product->brand->slug]) }}" 
                                   class="font-medium text-blue-600 hover:text-indigo-700">
                                    {{ $product->brand->name }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs: Description & Reviews -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8" x-data="{ activeTab: 'description' }">
            <!-- Tab Headers -->
            <div class="border-b">
                <nav class="flex">
                    <button @click="activeTab = 'description'"
                            :class="{ 'border-blue-600 text-blue-600': activeTab === 'description' }"
                            class="px-6 py-4 border-b-2 font-medium transition">
                        Description
                    </button>
                    <button @click="activeTab = 'reviews'"
                            :class="{ 'border-blue-600 text-blue-600': activeTab === 'reviews' }"
                            class="px-6 py-4 border-b-2 font-medium transition">
                        Reviews ({{ $product->reviews_count }})
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-8">
                <!-- Description Tab -->
                <div x-show="activeTab === 'description'" x-cloak>
                    <div class="prose max-w-none">
                        {!! $product->description !!}
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div x-show="activeTab === 'reviews'" x-cloak>
                    @if($product->approvedReviews->count() > 0)
                        <div class="space-y-6">
                            @foreach($product->approvedReviews as $review)
                                <div class="border-b pb-6 last:border-b-0">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                                                {{ substr($review->customer->name, 0, 1) }}
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <h4 class="font-semibold">{{ $review->customer->name }}</h4>
                                                @if($review->is_verified_purchase)
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                                                        Verified Purchase
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="flex text-yellow-400">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        @if($i <= $review->rating)
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
                                                <span class="text-sm text-gray-500">{{ $review->created_at->diffForHumans() }}</span>
                                            </div>
                                            @if($review->title)
                                                <h5 class="font-medium mb-2">{{ $review->title }}</h5>
                                            @endif
                                            @if($review->comment)
                                                <p class="text-gray-700">{{ $review->comment }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">No reviews yet. Be the first to review this product!</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($relatedProducts as $relatedProduct)
                        <livewire:product-card :product="$relatedProduct" :key="'related-' . $relatedProduct->id"  />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
