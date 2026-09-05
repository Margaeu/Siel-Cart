<div>
    <!-- Hero Banner Carousel -->
    @if($banners->isNotEmpty())
        <section
            x-data="{
                active: 0,
                total: {{ $banners->count() }},
                timer: null,
                start() {
                    if (this.total <= 1) return;
                    this.timer = setInterval(() => { this.next() }, 6000);
                },
                next() { this.active = (this.active + 1) % this.total },
                prev() { this.active = (this.active - 1 + this.total) % this.total },
            }"
            x-init="start()"
            class="relative overflow-hidden bg-[var(--color-primary)]"
        >
            <div class="relative h-[340px] md:h-[460px] lg:h-[520px]">
                @foreach($banners as $banner)
                    <div
                        x-show="active === {{ $loop->index }}"
                        x-cloak
                        x-transition:enter="transition ease-out duration-700"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0"
                    >
                        @if($banner->link_url)
                            <a href="{{ $banner->link_url }}" class="block w-full h-full">
                        @endif

                        <img src="{{ $banner->image_url }}"
                             alt="{{ $banner->title ?? config('app.name') . ' banner' }}"
                             class="w-full h-full object-cover">

                        @if($banner->title || $banner->subtitle)
                            <div class="absolute inset-0 bg-black/30 flex items-center">
                                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
                                    <div class="max-w-xl text-white">
                                        @if($banner->title)
                                            <h1 class="text-3xl md:text-5xl font-bold mb-3 tracking-tight">
                                                {{ $banner->title }}
                                            </h1>
                                        @endif
                                        @if($banner->subtitle)
                                            <p class="text-lg md:text-xl text-[var(--color-secondary)] font-medium">
                                                {{ $banner->subtitle }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($banner->link_url)
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($banners->count() > 1)
                <!-- Prev / Next Arrows -->
                <button @click="prev()"
                        aria-label="Previous banner"
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition backdrop-blur-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button @click="next()"
                        aria-label="Next banner"
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition backdrop-blur-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Dots -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                    @foreach($banners as $banner)
                        <button @click="active = {{ $loop->index }}"
                                aria-label="Go to banner {{ $loop->iteration }}"
                                :style="active === {{ $loop->index }} ? 'background-color: var(--color-secondary);' : ''"
                                class="w-2.5 h-2.5 rounded-full transition"
                                :class="active === {{ $loop->index }} ? '' : 'bg-white/50 hover:bg-white/80'">
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <!-- Fallback hero (shown until banners are uploaded in the admin) -->
        <section class="bg-[var(--color-primary)] text-white py-20 relative overflow-hidden">
            <!-- Subtle background accent element -->
            <div class="absolute -right-10 -bottom-10 w-96 h-96 bg-[var(--color-secondary)]/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="text-center">
                    <h1 class="text-4xl md:text-6xl font-bold mb-4 tracking-tight">
                        Welcome to {{ config('app.name') }}
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-[var(--color-secondary)] font-medium">
                        Discover amazing products at unbeatable prices
                    </p>
                    <a href="{{ route('products.index') }}" 
                       class="inline-block bg-[var(--color-secondary)] text-[var(--color-primary)] px-8 py-3.5 rounded-lg font-bold hover:bg-amber-400 active:bg-amber-500 transition shadow-lg transform hover:-translate-y-0.5">
                        Shop Now
                    </a>
                </div>
            </div>
        </section>
    @endif

    <!-- Categories Section -->
    <section class="py-16 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 border-l-4 border-[var(--color-primary)] pl-3">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                @foreach($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" 
                       class="group">
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-3 border border-gray-100 group-hover:border-[var(--color-secondary)] transition">
                            @if($category->image)
                                <img src="{{ asset('storage/' . $category->image) }}" 
                                     alt="{{ $category->name }}"
                                     class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-[var(--color-primary)]">
                                    <span class="text-4xl text-[var(--color-secondary)] font-bold">{{ substr($category->name, 0, 1) }}</span>
                                </div>
                            @endif
                        </div>
                        <h3 class="text-center font-medium text-gray-900 group-hover:text-[var(--color-primary)] transition">
                            {{ $category->name }}
                        </h3>
                        <p class="text-center text-sm text-gray-500">{{ $category->products_count }} items</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-16 bg-emerald-50/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold text-gray-900 border-l-4 border-[var(--color-secondary)] pl-3">Featured Products</h2>
                <a href="{{ route('products.index', ['featured' => 1]) }}" 
                   class="text-[var(--color-primary)] hover:text-[var(--color-secondary)] font-semibold transition">
                    View All →
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                    <livewire:product-card :product="$product" :key="$product->id" lazy />
                @endforeach
            </div>
        </div>
    </section>

    <!-- New Arrivals -->
    <section class="py-16 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold text-gray-900 border-l-4 border-[var(--color-primary)] pl-3">New Arrivals</h2>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" 
                   class="text-[var(--color-primary)] hover:text-[var(--color-secondary)] font-semibold transition">
                    View All →
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($newArrivals as $product)
                    <livewire:product-card :product="$product" :key="'new-' . $product->id" />
                @endforeach
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-16 bg-[var(--color-primary)]/5 border-t border-emerald-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Quality Guarantee -->
                <div class="text-center p-6 bg-white rounded-lg shadow-sm border border-emerald-100">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-50 text-[var(--color-primary)] rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900">Quality Guarantee</h3>
                    <p class="text-gray-600">All products are carefully selected and quality tested</p>
                </div>

                <!-- Fast Shipping -->
                <div class="text-center p-6 bg-white rounded-lg shadow-sm border border-[#FEF8EA]">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-[#FEF8EA] text-[var(--color-secondary)] rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900">Fast Shipping</h3>
                    <p class="text-gray-600">Quick delivery right to your doorstep</p>
                </div>

                <!-- Secure Payment -->
                <div class="text-center p-6 bg-white rounded-lg shadow-sm border border-emerald-100">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-50 text-[var(--color-primary)] rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900">Secure Payment</h3>
                    <p class="text-gray-600">Your payment information is safe with us</p>
                </div>
            </div>
        </div>
    </section>
</div>