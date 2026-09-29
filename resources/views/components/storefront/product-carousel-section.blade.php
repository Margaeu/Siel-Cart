{{--
    Shared horizontal product carousel for the homepage's ranked sections
    (Best Sellers, Top Picks). Featured Products keeps its own markup as-is;
    this exists so the two new sections don't duplicate it a second time.

    Props:
    - heading, description: section copy.
    - products: Collection of eligible Product models, already ranked.
    - badge: 'best_seller' | 'top_pick', passed through to each card and
      used to namespace its Livewire key so the same product can appear in
      both sections without a duplicate-key error.
    - emptyHeading, emptyMessage: shown when $products is empty.
    - showCategory: display each product's assigned category above its card.
    - sectionBgClass, accentBorderClass: section background and the empty
      state's dashed border, so each section reads as visually distinct.
--}}
@props([
    'heading',
    'description',
    'products',
    'badge',
    'emptyHeading',
    'emptyMessage',
    'sectionBgClass' => 'bg-white',
    'accentBorderClass' => 'border-gray-200',
    'ariaLabel' => null,
    'showCategory' => false,
])

<section class="{{ $sectionBgClass }} py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h2 class="border-l-4 border-[var(--color-secondary)] pl-3 text-2xl font-bold text-gray-900">{{ $heading }}</h2>
            <p class="mt-2 pl-3 text-sm text-gray-500">{{ $description }}</p>
        </div>

        @if($products->isNotEmpty())
            <div
                x-data="{
                    canScrollLeft: false,
                    canScrollRight: true,
                    updateScrollState() {
                        const el = $refs.track;
                        this.canScrollLeft = el.scrollLeft > 8;
                        this.canScrollRight = el.scrollLeft < el.scrollWidth - el.clientWidth - 8;
                    },
                    scrollByCard(direction) {
                        const el = $refs.track;
                        const card = el.querySelector('[data-carousel-item]');
                        const gap = parseFloat(getComputedStyle(el).columnGap) || 0;
                        const distance = card ? card.getBoundingClientRect().width + gap : el.clientWidth * 0.8;
                        el.scrollBy({ left: direction * distance, behavior: 'smooth' });
                    }
                }"
                x-init="$nextTick(() => updateScrollState())"
                class="relative"
                role="region"
                aria-label="{{ $ariaLabel ?? $heading }}"
            >
                <x-storefront.carousel-mobile-controls />

                <!-- Left arrow -->
                <button
                    type="button"
                    x-show="canScrollLeft"
                    x-cloak
                    x-on:click="scrollByCard(-1)"
                    class="absolute left-0 top-[38%] z-10 hidden size-11 -translate-x-4 -translate-y-1/2 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-black/5 transition hover:bg-gray-50 hover:text-[var(--color-primary)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] sm:flex"
                    aria-label="Scroll to previous products"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Scrollable track -->
                <div
                    x-ref="track"
                    x-on:scroll.debounce.75ms="updateScrollState()"
                    x-on:resize.window="updateScrollState()"
                    class="flex items-start gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 sm:gap-6 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    @foreach($products as $product)
                        <div data-carousel-item class="w-[42vw] shrink-0 snap-start sm:w-[13rem] lg:w-[13.5rem]">
                            @if($showCategory)
                                <h3 class="mb-4 flex min-h-11 items-end border-b border-black pb-2 text-xs font-bold uppercase leading-snug tracking-wider text-black sm:text-sm">
                                    <span class="min-w-0 break-words">{{ $product->category?->name }}</span>
                                </h3>
                            @endif
                            <livewire:product-card :product="$product" :badge="$badge" :key="$badge.'-'.$product->id" />
                        </div>
                    @endforeach
                </div>

                <!-- Right arrow -->
                <button
                    type="button"
                    x-show="canScrollRight"
                    x-cloak
                    x-on:click="scrollByCard(1)"
                    class="absolute right-0 top-[38%] z-10 hidden size-11 -translate-y-1/2 translate-x-4 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-black/5 transition hover:bg-gray-50 hover:text-[var(--color-primary)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] sm:flex"
                    aria-label="Scroll to next products"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        @else
            <div class="rounded-xl border border-dashed {{ $accentBorderClass }} bg-white/60 py-14 text-center">
                <p class="text-gray-500">{{ $emptyHeading }}</p>
                <p class="mt-1 text-sm text-gray-400">{{ $emptyMessage }}</p>
            </div>
        @endif
    </div>
</section>
