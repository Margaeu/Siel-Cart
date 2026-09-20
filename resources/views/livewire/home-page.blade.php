<div>
    <!-- Hero Banner Carousel -->
    @if($banners->isNotEmpty())
        <section
            x-data="{
                active: 0,
                position: 1,
                total: {{ $banners->count() }},
                timer: null,
                animate: true,
                dragging: false,
                dragStartX: 0,
                dragOffset: 0,
                prefersReducedMotion() {
                    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },
                start() {
                    this.stop();

                    if (this.total <= 1 || this.prefersReducedMotion()) {
                        return;
                    }

                    this.timer = window.setInterval(() => this.next(), 6000);
                },
                stop() {
                    if (this.timer) {
                        window.clearInterval(this.timer);
                        this.timer = null;
                    }
                },
                restart() {
                    this.start();
                },
                next() {
                    if (this.total <= 1) return;

                    if (this.prefersReducedMotion()) {
                        this.active = (this.active + 1) % this.total;
                        this.position = this.active + 1;
                        return;
                    }

                    this.animate = true;
                    this.position += 1;
                    this.active = (this.active + 1) % this.total;
                },
                prev() {
                    if (this.total <= 1) return;

                    if (this.prefersReducedMotion()) {
                        this.active = (this.active - 1 + this.total) % this.total;
                        this.position = this.active + 1;
                        return;
                    }

                    this.animate = true;
                    this.position -= 1;
                    this.active = (this.active - 1 + this.total) % this.total;
                },
                goTo(index) {
                    this.animate = true;
                    this.position = index + 1;
                    this.active = index;
                    this.restart();
                },
                settleLoop() {
                    if (this.position !== 0 && this.position !== this.total + 1) return;

                    this.animate = false;
                    this.position = this.position === 0 ? this.total : 1;

                    window.requestAnimationFrame(() => {
                        window.requestAnimationFrame(() => {
                            this.animate = true;
                        });
                    });
                },
                beginSwipe(event) {
                    if (this.total <= 1) return;

                    this.stop();
                    this.animate = false;
                    this.dragging = true;
                    this.dragStartX = event.touches[0].clientX;
                    this.dragOffset = 0;
                },
                moveSwipe(event) {
                    if (!this.dragging) return;

                    this.dragOffset = event.touches[0].clientX - this.dragStartX;
                },
                endSwipe(event) {
                    if (!this.dragging) return;

                    const distance = event.changedTouches[0].clientX - this.dragStartX;
                    this.dragging = false;
                    this.dragOffset = 0;
                    this.animate = true;

                    if (Math.abs(distance) >= 50) {
                        distance < 0 ? this.next() : this.prev();
                    }

                    this.start();
                },
            }"
            x-init="start()"
            x-on:mouseenter="stop()"
            x-on:mouseleave="start()"
            x-on:focusin="stop()"
            x-on:focusout="if (!$el.contains($event.relatedTarget)) start()"
            class="group/carousel relative overflow-hidden bg-[var(--color-primary)]"
            aria-roledescription="carousel"
            aria-label="Featured promotions"
        >
            <div
                class="relative h-[clamp(18rem,40vw,34rem)] touch-pan-y select-none overflow-hidden"
                x-on:touchstart.passive="beginSwipe($event)"
                x-on:touchmove.passive="moveSwipe($event)"
                x-on:touchend="endSwipe($event)"
                x-on:touchcancel="dragging = false; dragOffset = 0; animate = true; start()"
            >
                <div
                    x-ref="track"
                    class="flex h-full will-change-transform motion-reduce:transition-none"
                    style="transform: translate3d(-100%, 0, 0)"
                    x-bind:class="animate && !dragging ? 'transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]' : 'transition-none'"
                    x-bind:style="'transform: translate3d(calc(-' + (position * 100) + '% + ' + dragOffset + 'px), 0, 0)'"
                    x-on:transitionend.self="settleLoop()"
                >
                    {{-- Cloned edge slides make the first/last transition loop without a visible jump. --}}
                    <div
                        class="relative h-full w-full shrink-0"
                        aria-hidden="true"
                    >
                        <x-storefront.banner-slide :banner="$banners->last()" />
                    </div>

                    @foreach($banners as $banner)
                        <div
                            class="relative h-full w-full shrink-0"
                            role="group"
                            aria-roledescription="slide"
                            aria-label="{{ $loop->iteration }} of {{ $loop->count }}"
                            x-bind:aria-hidden="active !== {{ $loop->index }}"
                        >
                            <x-storefront.banner-slide
                                :banner="$banner"
                                :interactive="true"
                                :index="$loop->index"
                            />
                        </div>
                    @endforeach

                    <div
                        class="relative h-full w-full shrink-0"
                        aria-hidden="true"
                    >
                        <x-storefront.banner-slide :banner="$banners->first()" />
                    </div>
                </div>
            </div>

            @if($banners->count() > 1)
                <!-- Prev / Next Arrows -->
                <button
                        type="button"
                        x-on:click="prev(); restart()"
                        aria-label="Previous banner"
                        class="pointer-events-none absolute left-3 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/25 bg-black/20 text-white opacity-0 shadow-lg backdrop-blur-sm transition hover:bg-black/40 focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] group-hover/carousel:pointer-events-auto group-hover/carousel:opacity-100 sm:left-6">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button
                        type="button"
                        x-on:click="next(); restart()"
                        aria-label="Next banner"
                        class="pointer-events-none absolute right-3 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/25 bg-black/20 text-white opacity-0 shadow-lg backdrop-blur-sm transition hover:bg-black/40 focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] group-hover/carousel:pointer-events-auto group-hover/carousel:opacity-100 sm:right-6">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Dots -->
                <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-2 rounded-full bg-black/20 px-3 py-2 backdrop-blur-sm sm:bottom-7">
                    @foreach($banners as $banner)
                        <button
                                type="button"
                                x-on:click="goTo({{ $loop->index }})"
                                aria-label="Go to banner {{ $loop->iteration }}"
                                x-bind:aria-current="active === {{ $loop->index }} ? 'true' : null"
                                class="size-2.5 rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] focus-visible:ring-offset-2 focus-visible:ring-offset-black/40"
                                x-bind:class="active === {{ $loop->index }} ? 'w-7 bg-[var(--color-secondary)]' : 'bg-white/75 hover:bg-white'"
                        >
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
                        Discover the official merchandise of UPC!
                    </p>
                    <a href="{{ route('products.index') }}" 
                       class="inline-block bg-[var(--color-secondary)] text-[var(--color-primary)] px-8 py-3.5 rounded-lg font-bold hover:bg-amber-400 active:bg-amber-500 transition shadow-lg transform hover:-translate-y-0.5">
                        Shop Now
                    </a>
                </div>
            </div>
        </section>
    @endif

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

            <div
                x-data="{
                    canScrollLeft: false,
                    canScrollRight: true,
                    updateScrollState() {
                        const el = $refs.featuredTrack;
                        this.canScrollLeft = el.scrollLeft > 8;
                        this.canScrollRight = el.scrollLeft < el.scrollWidth - el.clientWidth - 8;
                    },
                    scrollByCard(direction) {
                        const el = $refs.featuredTrack;
                        const card = el.querySelector('[data-carousel-item]');
                        const distance = card ? card.getBoundingClientRect().width + 24 : el.clientWidth * 0.8;
                        el.scrollBy({ left: direction * distance, behavior: 'smooth' });
                    }
                }"
                x-init="updateScrollState()"
                class="relative"
            >
                <!-- Left arrow -->
                <button
                    type="button"
                    x-show="canScrollLeft"
                    x-cloak
                    x-on:click="scrollByCard(-1)"
                    class="absolute left-0 top-[38%] z-10 hidden size-11 -translate-x-4 -translate-y-1/2 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-black/5 transition hover:bg-gray-50 hover:text-[var(--color-primary)] sm:flex"
                    aria-label="Scroll to previous products"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Scrollable track -->
                <div
                    x-ref="featuredTrack"
                    x-on:scroll.debounce.75ms="updateScrollState()"
                    x-on:resize.window="updateScrollState()"
                    class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 sm:gap-6 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    @foreach($featuredProducts as $product)
                        <div data-carousel-item class="w-[42vw] shrink-0 snap-start sm:w-[240px] lg:w-[260px]">
                            <livewire:product-card :product="$product" :key="$product->id" />
                        </div>
                    @endforeach
                </div>

                <!-- Right arrow -->
                <button
                    type="button"
                    x-show="canScrollRight"
                    x-cloak
                    x-on:click="scrollByCard(1)"
                    class="absolute right-0 top-[38%] z-10 hidden size-11 -translate-y-1/2 translate-x-4 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-black/5 transition hover:bg-gray-50 hover:text-[var(--color-primary)] sm:flex"
                    aria-label="Scroll to next products"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-16 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 border-l-4 border-[var(--color-secondary)] pl-3">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                @foreach($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" 
                       class="group">
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-3 border border-gray-100 group-hover:border-[var(--color-secondary)] transition">
                            @if($category->image)
                                <img src="{{ $category->image_url }}" 
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
    
    <!-- Benefits Section 
    <section class="py-16 bg-[var(--color-primary)]/5 border-t border-emerald-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                Quality Guarantee 
                <div class="text-center p-6 bg-white rounded-lg shadow-sm border border-emerald-100">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-50 text-[var(--color-primary)] rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900">Quality Guarantee</h3>
                    <p class="text-gray-600">All products are carefully selected and quality tested</p>
                </div>

              Fast Shipping
                <div class="text-center p-6 bg-white rounded-lg shadow-sm border border-[#FEF8EA]">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-[#FEF8EA] text-[var(--color-secondary)] rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900">Fast Shipping</h3>
                    <p class="text-gray-600">Quick delivery right to your doorstep</p>
                </div>
    

               Secure Payment 
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
    -->
</div>