@php
    // Which filter groups are actually narrowing the catalog right now.
    //
    // maxPrice is seeded to the top of the range in mount(), and minPrice to
    // '', so neither is "active" just by being set -- a bound only counts once
    // it sits inside the range. Without that test the Filters button would
    // claim an active price filter on a page nobody has filtered.
    $priceFloor = (float) ($priceRange[0] ?? 0);
    $priceCeiling = (float) ($priceRange[1] ?? 0);

    $minPriceActive = $minPrice !== '' && (float) $minPrice > $priceFloor;
    $maxPriceActive = $maxPrice !== '' && $priceCeiling > 0 && (float) $maxPrice < $priceCeiling;

    $activeFilterCount = collect([
        (bool) $category,
        $minPriceActive || $maxPriceActive,
        (bool) $inStock,
    ])->filter()->count();

    $sortOptions = [
        'newest' => 'Newest',
        'price_low' => 'Price: Low to High',
        'price_high' => 'Price: High to Low',
        'popular' => 'Popular',
    ];
    $currentSortLabel = $sortOptions[$sort] ?? $sortOptions['newest'];

    // Shared control chrome. Every interactive target on this page clears the
    // 44px minimum, which is why these all carry a min-h-11 rather than
    // padding that happens to add up on one breakpoint and not another.
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2';
    $groupHeading = 'flex min-h-11 w-full items-center justify-between text-xs font-bold uppercase tracking-[0.12em] text-gray-900 ' . $focusRing;
@endphp

<div
    class="min-h-screen bg-white text-gray-800"
    x-data="{
        // The sidebar and the small-screen panel are the same markup but not
        // the same control: on desktop it is a persistent sidebar the customer
        // can hide, below that it is a disclosure that starts closed so the
        // products stay near the top of the first viewport.
        desktop: window.matchMedia('(min-width: 1024px)').matches,
        showFilters: true,
        mobileFiltersOpen: false,
        get filtersVisible() { return this.desktop ? this.showFilters : this.mobileFiltersOpen },
        init() {
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
                this.desktop = event.matches;
                // Crossing the breakpoint with the panel open would otherwise
                // leave the disclosure's open state behind on the sidebar.
                this.mobileFiltersOpen = false;
            });
        },
    }"
>
    <div class="mx-auto max-w-7xl px-4 pt-6 pb-4 sm:px-6 sm:pt-8 sm:pb-6 lg:px-8">

        {{-- Breadcrumb --}}
        <nav class="mb-3 text-xs text-gray-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="rounded transition hover:text-gray-900 {{ $focusRing }}">Home</a></li>
                <li aria-hidden="true">/</li>
                <li class="font-medium text-gray-700" aria-current="page">Products</li>
            </ol>
        </nav>

        {{-- Catalog header --}}
        <div>
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-2xl font-extrabold tracking-[-0.02em] text-gray-900 sm:text-3xl">
                    @if($category)
                        {{ \App\Models\Category::where('slug', $category)->first()?->name ?? 'Category' }}
                    @elseif($search)
                        Search: "{{ $search }}"
                    @else
                        All Products
                    @endif
                </h1>
                <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">
                    {{ $products->total() }} {{ Str::plural('item', $products->total()) }}
                </span>
            </div>

            {{--
                The page's one piece of ornament: the ruled header of a
                university-office form, a short gold segment running into a
                hairline that carries the rest of the width. It marks the
                catalog as an official UBAP issuance rather than a shop banner,
                and it is deliberately the only decorative element here -- the
                sidebar and the cards stay plain so this reads as the accent.
            --}}
            <div class="mt-4 flex items-center" aria-hidden="true">
                <span class="h-[3px] w-14 rounded-full bg-[var(--color-secondary)]"></span>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>
        </div>

        {{-- Control bar: filters disclosure on the left, sorting always on the right --}}
        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
            {{-- Small-screen filter disclosure --}}
            <button
                type="button"
                x-on:click="mobileFiltersOpen = !mobileFiltersOpen"
                x-bind:aria-expanded="mobileFiltersOpen ? 'true' : 'false'"
                aria-controls="catalog-filters"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-gray-300 px-4 text-sm font-semibold text-gray-900 transition hover:border-gray-400 hover:bg-gray-50 lg:hidden {{ $focusRing }}"
            >
                <svg class="size-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M6 12h12M10 19h4" />
                </svg>
                <span>Filters</span>
                @if($activeFilterCount)
                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[11px] font-bold text-white">
                        {{ $activeFilterCount }}
                    </span>
                    <span class="sr-only">{{ $activeFilterCount }} {{ Str::plural('filter', $activeFilterCount) }} applied</span>
                @endif
            </button>

            {{-- Desktop sidebar toggle --}}
            <button
                type="button"
                x-on:click="showFilters = !showFilters"
                x-bind:aria-expanded="showFilters ? 'true' : 'false'"
                aria-controls="catalog-filters"
                class="hidden min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium text-gray-600 transition hover:text-gray-900 lg:inline-flex {{ $focusRing }}"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M6 12h12M10 19h4" />
                </svg>
                <span x-text="showFilters ? 'Hide filters' : 'Show filters'">Hide filters</span>
            </button>

            {{-- Sorting stays reachable without opening the filters --}}
            <div
                class="ml-auto flex w-full min-w-0 items-center justify-end gap-2 sm:w-auto"
                x-data="{
                    open: false,
                    focusOption(step) {
                        const options = [...this.$refs.menu.querySelectorAll('[role=menuitemradio]')];
                        const current = options.indexOf(document.activeElement);
                        const next = current === -1
                            ? (step > 0 ? 0 : options.length - 1)
                            : (current + step + options.length) % options.length;

                        options[next]?.focus();
                    },
                }"
                x-on:click.outside="open = false"
            >
                <span id="catalog-sort-label" class="shrink-0 text-xs font-semibold uppercase tracking-[0.12em] text-gray-500">Sort</span>

                <div class="relative min-w-0">
                    <button
                        id="catalog-sort-button"
                        x-ref="trigger"
                        type="button"
                        aria-haspopup="menu"
                        aria-labelledby="catalog-sort-label catalog-sort-value"
                        x-bind:aria-expanded="open ? 'true' : 'false'"
                        x-on:click="open = ! open"
                        x-on:keydown.arrow-down.prevent="open = true; $nextTick(() => focusOption(1))"
                        x-on:keydown.arrow-up.prevent="open = true; $nextTick(() => focusOption(-1))"
                        x-on:keydown.escape.prevent="open = false"
                        class="group flex h-11 w-56 max-w-[calc(100vw-6.5rem)] items-center justify-between gap-3 rounded-full border border-gray-300 bg-white px-4 text-left text-sm font-semibold text-gray-900 shadow-sm transition hover:border-gray-400 hover:shadow {{ $focusRing }}"
                    >
                        <span id="catalog-sort-value" class="min-w-0 truncate">{{ $currentSortLabel }}</span>
                        <svg
                            class="size-4 shrink-0 text-[var(--color-primary)] transition-transform duration-200"
                            x-bind:class="open ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div
                        x-ref="menu"
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="scale-95 opacity-0"
                        x-transition:enter-end="scale-100 opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="scale-100 opacity-100"
                        x-transition:leave-end="scale-95 opacity-0"
                        x-on:keydown.escape.stop.prevent="open = false; $refs.trigger.focus()"
                        x-on:keydown.arrow-down.prevent="focusOption(1)"
                        x-on:keydown.arrow-up.prevent="focusOption(-1)"
                        x-on:keydown.home.prevent="$el.querySelector('[role=menuitemradio]')?.focus()"
                        x-on:keydown.end.prevent="$el.querySelector('[role=menuitemradio]:last-of-type')?.focus()"
                        role="menu"
                        aria-labelledby="catalog-sort-button"
                        class="absolute right-0 z-30 mt-2 w-64 max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-2xl border border-gray-200 bg-white p-1.5 shadow-[0_18px_45px_-18px_rgba(17,24,39,0.35)]"
                    >
                        @foreach($sortOptions as $value => $label)
                            <button
                                type="button"
                                role="menuitemradio"
                                aria-checked="{{ $sort === $value ? 'true' : 'false' }}"
                                wire:click="$set('sort', '{{ $value }}')"
                                x-on:click="open = false; $nextTick(() => $refs.trigger.focus())"
                                class="flex min-h-11 w-full items-center justify-between gap-4 rounded-xl px-3.5 text-left text-sm transition {{ $sort === $value ? 'bg-[color-mix(in_srgb,var(--color-primary)_9%,white)] font-bold text-[var(--color-primary)]' : 'font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-950' }} {{ $focusRing }}"
                            >
                                <span>{{ $label }}</span>
                                @if($sort === $value)
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary)] text-white" aria-hidden="true">
                                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 12 4 4L19 6" />
                                        </svg>
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Main layout: sidebar beside the grid on desktop, stacked below it --}}
        <div class="flex flex-col items-start lg:flex-row lg:gap-10">

            <aside
                id="catalog-filters"
                x-show="filtersVisible"
                x-cloak
                {{--
                    Deliberately no x-transition. Alpine's leave transition
                    stopped restoring display:none after the first close, which
                    left the panel at opacity:0 but still occupying its full
                    height -- the products were pushed back down the page by an
                    invisible block. A plain x-show toggles display in one step,
                    and a fade buys this disclosure nothing.
                --}}
                aria-label="Product filters"
                class="w-full shrink-0
                       max-lg:mb-6 max-lg:grid max-lg:gap-x-8 max-lg:rounded-2xl max-lg:border max-lg:border-gray-200 max-lg:p-5
                       sm:max-lg:grid-cols-2
                       lg:w-56 lg:space-y-7 lg:pt-1"
            >

                {{-- Categories --}}
                <div class="border-b border-gray-100 pb-4" x-data="{ open: true }">
                    <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="filter-category" class="{{ $groupHeading }}">
                        <span>Category</span>
                        <svg class="size-3.5 text-gray-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': !open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>

                    <div id="filter-category" x-show="open" class="text-sm">
                        <button
                            type="button"
                            wire:click="$set('category', '')"
                            @if(! $category) aria-current="true" @endif
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-1 text-left transition hover:bg-gray-50 {{ $focusRing }}"
                        >
                            <span class="flex items-center gap-2 {{ ! $category ? 'font-bold text-gray-900' : 'text-gray-600' }}">
                                <span class="h-4 w-[3px] shrink-0 rounded-full {{ ! $category ? 'bg-[var(--color-primary)]' : 'bg-transparent' }}" aria-hidden="true"></span>
                                All Products
                            </span>
                            <span data-testid="all-products-count" class="text-xs text-gray-500">{{ $allProductsCount }}</span>
                        </button>

                        @foreach($categories as $cat)
                            <button
                                type="button"
                                wire:click="$set('category', '{{ $cat->slug }}')"
                                @if($category === $cat->slug) aria-current="true" @endif
                                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-1 text-left transition hover:bg-gray-50 {{ $focusRing }}"
                            >
                                <span class="flex items-center gap-2 {{ $category === $cat->slug ? 'font-bold text-gray-900' : 'text-gray-600' }}">
                                    <span class="h-4 w-[3px] shrink-0 rounded-full {{ $category === $cat->slug ? 'bg-[var(--color-primary)]' : 'bg-transparent' }}" aria-hidden="true"></span>
                                    {{ $cat->name }}
                                </span>
                                {{--
                                    "Soon" used to sit at text-gray-300, which is
                                    below AA against white. gray-500 keeps it
                                    quieter than the category name while staying
                                    readable.
                                --}}
                                @if($cat->products_count > 0)
                                    <span class="text-xs text-gray-500">{{ $cat->products_count }}</span>
                                @else
                                    <span class="text-[11px] font-medium uppercase tracking-wide text-gray-500">0</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Price --}}
                <div class="border-b border-gray-100 pb-4" x-data="{ open: true }">
                    <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="filter-price" class="{{ $groupHeading }}">
                        <span>Price</span>
                        <svg class="size-3.5 text-gray-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': !open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>

                    <div id="filter-price" x-show="open" class="text-sm text-gray-700">
                        <div class="flex items-end gap-2 pt-2">
                            <div class="min-w-0 flex-1">
                                <label for="price-min" class="mb-1 block text-[11px] font-medium text-gray-500">Min</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-gray-500" aria-hidden="true">₱</span>
                                    <input id="price-min" type="number" min="0" inputmode="numeric" wire:model="minPrice" placeholder="0" class="h-11 w-full rounded-lg border border-gray-300 bg-white pl-6 pr-2 text-sm text-gray-900 {{ $focusRing }}">
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <label for="price-max" class="mb-1 block text-[11px] font-medium text-gray-500">Max</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-gray-500" aria-hidden="true">₱</span>
                                    <input id="price-max" type="number" min="0" inputmode="numeric" wire:model="maxPrice" placeholder="{{ (int) $priceCeiling }}" class="h-11 w-full rounded-lg border border-gray-300 bg-white pl-6 pr-2 text-sm text-gray-900 {{ $focusRing }}">
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="applyPriceFilter"
                                class="inline-flex h-11 min-w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $focusRing }}"
                            >
                                <span aria-hidden="true">Go</span>
                                <span class="sr-only">Apply price range</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Availability --}}
                <div class="max-lg:pb-1" x-data="{ open: true }">
                    <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="filter-availability" class="{{ $groupHeading }}">
                        <span>Availability</span>
                        <svg class="size-3.5 text-gray-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': !open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>

                    <div id="filter-availability" x-show="open">
                        {{--
                            The whole row is the switch, so the 44px target is
                            the label as well as the track -- the track alone is
                            20px tall and was the smallest target on the page.

                            Every binding reads $wire.inStock rather than a
                            local Alpine copy. A copy stayed switched on after
                            "Clear filters" reset the property server-side, so
                            the control claimed a filter that was no longer
                            being applied.
                        --}}
                        <button
                            type="button"
                            role="switch"
                            x-bind:aria-checked="$wire.inStock ? 'true' : 'false'"
                            x-on:click="$wire.set('inStock', ! $wire.inStock)"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-1 text-left text-sm text-gray-700 transition hover:bg-gray-50 {{ $focusRing }}"
                        >
                            <span>In stock only</span>
                            <span
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                x-bind:class="$wire.inStock ? 'bg-[var(--color-primary)]' : 'bg-gray-300'"
                                aria-hidden="true"
                            >
                                <span
                                    class="inline-block size-5 transform rounded-full bg-white shadow transition duration-200 ease-in-out"
                                    x-bind:class="$wire.inStock ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </span>
                        </button>
                    </div>
                </div>

            </aside>

            {{-- Products --}}
            <div class="w-full min-w-0 flex-1">
                @if ($products->count() > 0)
                    {{--
                        Content-driven columns rather than fixed card widths:
                        one column below 360px (two would leave ~128px cards on
                        a 320px phone), two up to the tablet breakpoint, three
                        from 768px, and a fourth only past 1440px so wide
                        desktops get more products instead of taller images.
                    --}}
                    <div class="grid grid-cols-1 gap-x-3 gap-y-7 xs:grid-cols-2 sm:gap-x-5 sm:gap-y-9 md:grid-cols-3 lg:gap-x-6 lg:gap-y-10 3xl:grid-cols-4">
                        @foreach ($products as $product)
                            <livewire:product-card :key="$product->id" :product="$product" />
                        @endforeach
                    </div>

                    {{--
                        Progress and Load more only exist while there is another
                        page. On a catalog that fits in one page the old block
                        reported "You've viewed 7 of 7" above a full bar, which
                        told the customer nothing and left a tall gap before the
                        footer.
                    --}}
                    @if ($products->hasMorePages())
                        @php
                            $percentage = $products->total() > 0
                                ? min(100, round(($products->count() / $products->total()) * 100))
                                : 0;
                        @endphp
                        <div class="mt-10 flex flex-col items-center gap-4 text-center">
                            <div class="w-48">
                                <p class="mb-2 text-xs text-gray-600">You've viewed {{ $products->count() }} of {{ $products->total() }} products</p>
                                <div class="h-1 w-full overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-full rounded-full bg-[var(--color-primary)] transition-all duration-300" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="loadMore"
                                class="inline-flex min-h-11 items-center justify-center rounded-full border border-gray-300 bg-white px-8 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 {{ $focusRing }}"
                            >
                                Load more
                            </button>
                        </div>
                    @endif
                @else
                    {{-- Empty state --}}
                    <div class="rounded-2xl bg-[#fafaf7] px-6 py-14 text-center">
                        <h2 class="mb-1 text-base font-semibold text-gray-900">No products match those filters</h2>
                        <p class="mb-6 text-sm text-gray-600">Try a wider price range, or pick a different category.</p>
                        <button
                            type="button"
                            wire:click="clearFilters"
                            class="inline-flex min-h-11 items-center rounded-full bg-[var(--color-primary)] px-5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] {{ $focusRing }}"
                        >
                            Clear filters
                        </button>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
