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
                /*
                 * Clip playback for the whole track. The slides used to carry
                 * `src` + `autoplay` each, which downloaded and decoded every
                 * clip -- the two cloned edge slides included -- on every
                 * homepage visit; a `preload=metadata` hint did not prevent that,
                 * because `autoplay` overrides it.
                 *
                 * Keyed on `position`, not `active`: `position` is the track
                 * index, so it addresses the clones as well as the real slides,
                 * and exactly one element is ever the one on screen. That
                 * matters during a loop transition, where a clone is what the
                 * visitor is looking at for the full 700ms -- gating on `active`
                 * would leave it blank. A clone shares its URL with the real
                 * slide it copies, so it is served from the HTTP cache rather
                 * than fetched again.
                 *
                 * Under prefers-reduced-motion nothing is loaded or played at
                 * all: motion-reduce:hidden already swaps each clip for the
                 * flat fallback, so fetching it would be motion the visitor
                 * asked not to see, paid for in bandwidth.
                 */
                syncVideos() {
                    const reduced = this.prefersReducedMotion();

                    this.$el.querySelectorAll('[data-slide-position]').forEach((slide) => {
                        const video = slide.querySelector('video[data-banner-video]');

                        if (!video) return;

                        if (reduced || Number(slide.dataset.slidePosition) !== this.position) {
                            video.pause();

                            return;
                        }

                        // Assigned on first use only: re-assigning an identical
                        // src restarts the clip from zero mid-play.
                        if (!video.getAttribute('src') && video.dataset.src) {
                            video.setAttribute('src', video.dataset.src);
                        }

                        // play() rejects when the browser declines (a data-saver
                        // mode, or a decode failure). It is decorative, so the
                        // slide simply stays on its background colour.
                        const played = video.play();

                        if (played && typeof played.catch === 'function') {
                            played.catch(() => {});
                        }
                    });
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
            x-init="start(); syncVideos(); $watch('position', () => syncVideos())"
            x-on:mouseenter="stop()"
            x-on:mouseleave="start()"
            x-on:focusin="stop()"
            x-on:focusout="if (!$el.contains($event.relatedTarget)) start()"
            class="group/carousel relative overflow-hidden bg-[var(--color-primary)]"
            aria-roledescription="carousel"
            aria-label="Featured promotions"
        >
            <div
                class="relative h-[clamp(19rem,32vw,27.5rem)] touch-pan-y select-none overflow-hidden"
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
                    <div class="relative h-full w-full shrink-0" aria-hidden="true" data-slide-position="0">
                        <x-storefront.banner-slide :banner="$banners->last()" />
                    </div>

                    @foreach($banners as $banner)
                        <div
                            class="relative h-full w-full shrink-0"
                            data-slide-position="{{ $loop->iteration }}"
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

                    <div class="relative h-full w-full shrink-0" aria-hidden="true" data-slide-position="{{ $banners->count() + 1 }}">
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
        <section class="relative overflow-hidden bg-[var(--color-primary)] py-16 lg:py-20 text-white">
            <!-- Soft background accents -->
            <div class="pointer-events-none absolute -right-16 -top-16 h-80 w-80 rounded-full bg-[var(--color-secondary)]/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-16 h-96 w-96 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <span class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-[var(--color-secondary)] ring-1 ring-inset ring-white/15">
                    Official campus store
                </span>

                <h1 class="text-3xl font-bold tracking-tight md:text-5xl">
                    Welcome to {{ config('app.name') }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-white/85 md:text-lg">
                    Discover the official merchandise of UPC.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('products.index') }}"
                       class="inline-block transform rounded-lg bg-[var(--color-secondary)] px-8 py-3.5 font-bold text-[var(--color-primary)] shadow-lg transition hover:-translate-y-0.5 hover:bg-[var(--color-secondary-hover)] active:bg-[var(--color-secondary-active)]">
                        Shop Now
                    </a>
                </div>
            </div>
        </section>
    @endif

    <!-- Featured Products -->
    <section class="bg-[#F9FAFB] py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="border-l-4 border-[var(--color-secondary)] pl-3 text-2xl font-bold text-gray-900">Featured Products</h2>
                <p class="mt-2 pl-3 text-sm text-gray-500">Hand-picked favorites from the store</p>
            </div>

            @if($featuredProducts->isNotEmpty())
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
                        class="flex items-start gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 sm:gap-6 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        @foreach($featuredProducts as $product)
                            <div data-carousel-item class="w-[42vw] shrink-0 snap-start sm:w-[13rem] lg:w-[13.5rem]">
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
            @else
                <div class="rounded-xl border border-dashed border-emerald-200 bg-white/60 py-14 text-center">
                    <p class="text-gray-500">No featured products yet — check back soon.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Best Sellers -->
    <x-storefront.product-carousel-section
        heading="Best Sellers"
        description="The top seller in each category over the last 7 days."
        :products="$bestSellers"
        badge="best_seller"
        aria-label="Best sellers"
        section-bg-class="bg-[#F9FAFB]"
        accent-border-class="border-amber-200"
        empty-heading="No Best Sellers yet."
        empty-message="Check back once this week's completed orders bring in some sales."
    />

    <!-- Top Picks -->
    <x-storefront.product-carousel-section
        heading="Top Picks"
        description="The store's top sellers across every category over the last 7 days."
        :products="$topPicks"
        badge="top_pick"
        aria-label="Top picks"
        section-bg-class="bg-[#F9FAFB]"
        accent-border-class="border-amber-200"
        empty-heading="No Top Picks yet."
        empty-message="Check back once this week's completed orders bring in some sales."
    />

    <!-- Categories Section -->
    <section class="bg-[#F9FAFB] py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="border-l-4 border-[var(--color-secondary)] pl-3 text-2xl font-bold text-gray-900">Shop by Category</h2>
                <p class="mt-2 pl-3 text-sm text-gray-500">Find exactly what you're looking for</p>
            </div>
            <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-6">
                @foreach($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}"
                       class="group block">
                        <div class="relative mb-3 aspect-square overflow-hidden rounded-2xl bg-[var(--color-primary)] shadow-sm ring-1 ring-black/5 transition-all duration-300 group-hover:shadow-lg">
                            {{--
                                Every category gets the same letter-avatar backdrop first, so a missing
                                or broken photo never shows as an empty/broken tile — the image (when it
                                loads) simply layers on top and hides it via onerror.
                            --}}
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="flex size-16 items-center justify-center rounded-full bg-white/10 text-3xl font-bold text-[var(--color-secondary)] sm:size-20 sm:text-4xl">
                                    {{ substr($category->name, 0, 1) }}
                                </span>
                            </div>

                            @if($category->image)
                                <img src="{{ $category->image_url }}"
                                     alt="{{ $category->name }}"
                                     onerror="this.remove();"
                                     class="absolute inset-0 h-full w-full object-cover transition duration-500 ease-out group-hover:scale-110">
                            @endif

                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>

                            <!-- Name + count sit on the image itself, so the tile reads as one clean unit -->
                            <div class="absolute inset-x-0 bottom-0 p-3">
                                <h3 class="text-sm font-semibold leading-snug text-white sm:text-base">
                                    {{ $category->name }}
                                </h3>
                                <p class="mt-0.5 text-[0.6875rem] text-white/75 sm:text-xs">{{ $category->products_count }} items</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</div>
