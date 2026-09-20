<div class="bg-white min-h-screen text-gray-800" x-data="{ showFilters: true, inStockOnly: false }">

    {{-- Top Announcement Bar --}}
    <div class="border-b border-gray-100 bg-[#fbfbfa] py-2 text-xs text-gray-500">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-center gap-6 sm:gap-10">
            <div class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Pickup at the UBAP Office, CLSU</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>Pay in cash when you claim</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>We'll email you when it's ready</span>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        {{-- Breadcrumb --}}
        <nav class="mb-3 text-xs text-gray-400">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="hover:text-gray-600 transition">Home</a></li>
                <li>/</li>
                <li class="text-gray-600">Shop</li>
            </ol>
        </nav>

        {{-- Main Page Title & Top Control Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-baseline justify-between pb-6 gap-4">
            <div class="flex items-baseline gap-2.5">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                    @if($category)
                        {{ \App\Models\Category::where('slug', $category)->first()?->name ?? 'Category' }}
                    @elseif($search)
                        Search: "{{ $search }}"
                    @else
                        All Products
                    @endif
                </h1>
                <span class="text-xs font-medium text-gray-400">{{ $products->total() }} items</span>
            </div>

            {{-- Right Controls: Hide Filters & Sort --}}
            <div class="flex items-center gap-6 self-end sm:self-auto text-xs">
                <button @click="showFilters = !showFilters" class="hidden lg:flex items-center gap-1.5 text-gray-600 hover:text-gray-900 transition">
                    <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'"></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </button>

                <div class="flex items-center gap-1.5 text-gray-600">
                    <span>Sort by:</span>
                    <select wire:model.live="sort" class="border-0 bg-transparent py-0 pl-1 pr-6 font-semibold text-gray-900 text-xs focus:ring-0 cursor-pointer">
                        <option value="newest">Newest</option>
                        <option value="price_low">Price: Low to High</option>
                        <option value="price_high">Price: High to Low</option>
                        <option value="popular">Popular</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Main Layout Grid --}}
        <div class="flex flex-col lg:flex-row gap-8 lg:gap-12 items-start">

            {{-- Sidebar Filter Column --}}
            <aside x-show="showFilters" 
                   x-transition
                   class="w-full lg:w-56 shrink-0 space-y-6 pt-1">

                {{-- Categories Section --}}
                <div class="border-b border-gray-100 pb-5" x-data="{ open: true }">
                    <button @click="open = !open" class="flex items-center justify-between w-full text-xs font-bold uppercase tracking-wider text-gray-900 mb-3">
                        <span>Category</span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="{'rotate-180': !open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>

                    <div x-show="open" class="space-y-2 text-xs">
                        {{-- All Products item with active black bar indicator --}}
                        <button wire:click="$set('category', '')" 
                                class="w-full flex items-center justify-between text-left group py-0.5">
                            <span class="flex items-center gap-2 {{ !$category ? 'font-bold text-gray-900' : 'text-gray-500 hover:text-gray-900' }}">
                                @if(!$category)
                                    <span class="w-0.5 h-3.5 bg-gray-900 inline-block rounded-full"></span>
                                @endif
                                All Products
                            </span>
                            <span class="text-[11px] text-gray-400">{{ $products->total() }}</span>
                        </button>

                        @foreach($categories as $cat)
                            <button wire:click="$set('category', '{{ $cat->slug }}')" 
                                    class="w-full flex items-center justify-between text-left group py-0.5">
                                <span class="flex items-center gap-2 {{ $category === $cat->slug ? 'font-bold text-gray-900' : 'text-gray-500 hover:text-gray-900' }}">
                                    @if($category === $cat->slug)
                                        <span class="w-0.5 h-3.5 bg-gray-900 inline-block rounded-full"></span>
                                    @endif
                                    {{ $cat->name }}
                                </span>
                                <span class="text-[11px] {{ $cat->products_count > 0 ? 'text-gray-400' : 'text-gray-300' }}">
                                    {{ $cat->products_count > 0 ? $cat->products_count : 'Soon' }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Price Filter Section --}}
                <div class="border-b border-gray-100 pb-5" x-data="{ open: true }">
                    <button @click="open = !open" class="flex items-center justify-between w-full text-xs font-bold uppercase tracking-wider text-gray-900 mb-3">
                        <span>Price</span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="{'rotate-180': !open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>

                    <div x-show="open" class="space-y-2.5 text-xs text-gray-600">
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-gray-900">
                            <input type="checkbox" wire:click="$set('maxPrice', 300)" class="rounded border-gray-300 text-gray-900 focus:ring-0 h-3.5 w-3.5">
                            <span>Under ₱300</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-gray-900">
                            <input type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-0 h-3.5 w-3.5">
                            <span>₱300 – ₱500</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer hover:text-gray-900">
                            <input type="checkbox" wire:click="$set('minPrice', 500)" class="rounded border-gray-300 text-gray-900 focus:ring-0 h-3.5 w-3.5">
                            <span>Over ₱500</span>
                        </label>

                        {{-- Custom Price Inputs + Go button --}}
                        <div class="flex items-center gap-1.5 pt-2">
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 left-2 flex items-center text-[10px] text-gray-400">₱</span>
                                <input type="number" wire:model="minPrice" placeholder="Min" class="w-full text-xs pl-5 pr-2 py-1.5 border border-gray-200 rounded-md bg-white focus:outline-none focus:border-gray-400">
                            </div>
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 left-2 flex items-center text-[10px] text-gray-400">₱</span>
                                <input type="number" wire:model="maxPrice" placeholder="Max" class="w-full text-xs pl-5 pr-2 py-1.5 border border-gray-200 rounded-md bg-white focus:outline-none focus:border-gray-400">
                            </div>
                            <button wire:click="applyPriceFilter" class="bg-black hover:bg-gray-800 text-white text-[11px] font-semibold px-3 py-1.5 rounded-md transition shrink-0">
                                Go
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Availability Section --}}
                <div class="pb-5" x-data="{ open: true }">
                    <button @click="open = !open" class="flex items-center justify-between w-full text-xs font-bold uppercase tracking-wider text-gray-900 mb-3">
                        <span>Availability</span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="{'rotate-180': !open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>

                    <div x-show="open" class="flex items-center justify-between text-xs text-gray-600">
                        <span>In stock only</span>
                        {{-- Toggle Switch --}}
                        <button type="button" 
                                @click="inStockOnly = !inStockOnly; $wire.set('inStock', inStockOnly)"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="inStockOnly ? 'bg-black' : 'bg-gray-200'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition duration-200 ease-in-out shadow"
                                  :class="inStockOnly ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>
                </div>

            </aside>

            {{-- Products Grid Column --}}
            <div class="w-full flex-1">
                @if ($products->count() > 0)
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 sm:gap-x-6 gap-y-8 sm:gap-y-10">
                        @foreach ($products as $product)
                            <livewire:product-card :key="$product->id" :product="$product" />
                        @endforeach
                    </div>

                    {{-- Bottom Progress & Load More Indicator --}}
                    <div class="mt-16 flex flex-col items-center justify-center space-y-4 text-center">
                        <div class="w-48">
                            <p class="text-xs text-gray-500 mb-2">You've viewed {{ $products->count() }} of {{ $products->total() }} products</p>
                            <div class="w-full h-0.5 bg-gray-200 rounded-full overflow-hidden">
                                @php
                                    $percentage = $products->total() > 0 ? min(100, round(($products->count() / $products->total()) * 100)) : 0;
                                @endphp
                                <div class="h-full bg-gray-900 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        @if ($products->hasMorePages())
                            <button wire:click="loadMore" class="inline-flex items-center justify-center px-8 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-full hover:border-gray-400 hover:bg-gray-50 transition shadow-xs">
                                Load more
                            </button>
                        @endif
                    </div>
                @else
                    {{-- Empty State --}}
                    <div class="bg-[#fafaf7] py-16 px-6 rounded-2xl text-center">
                        <h3 class="text-base font-semibold text-gray-900 mb-1">No products found</h3>
                        <p class="text-xs text-gray-500 mb-6">Try adjusting your filters or search terms</p>
                        <button wire:click="clearFilters" 
                                class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-black rounded-lg hover:bg-gray-800 transition">
                            Clear filters
                        </button>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>