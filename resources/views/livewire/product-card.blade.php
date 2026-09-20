{{--
    Product Card Component
    Full-bleed image tile with overlay badges and hover reveal, then name,
    rating and price sitting directly on the page background (no white card box).
--}}
@php
    // A product counts as new for its first month on the shelf. Featured wins
    // over new, so a hand-picked product reads as featured rather than as one
    // more recent arrival.
    $isNew = $product->created_at?->gt(now()->subDays(30));
    $isOutOfStock = $product->stock_status === 'out_of_stock';
    $rating = $product->average_rating;
@endphp

<div class="group relative flex flex-col">
    <a href="{{ route('products.show', $product->slug) }}" class="block w-full">

        <!-- Product Image Tile -->
        <div class="relative aspect-[4/5] w-full overflow-hidden rounded-2xl bg-gray-100 shadow-sm ring-1 ring-black/5 transition-shadow duration-300 group-hover:shadow-lg">

            <!-- Status Badges (Top-Left) -->
            <div class="absolute top-2 left-2 z-10 flex flex-col items-start gap-1.5 sm:top-3 sm:left-3 sm:gap-2">
                @if($isOutOfStock)
                    <span class="rounded-full bg-gray-900/85 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white shadow-sm backdrop-blur-sm sm:px-3 sm:py-1 sm:text-[11px]">
                        Sold Out
                    </span>
                @elseif($product->is_featured)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--color-secondary)] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-[var(--color-primary)] shadow-sm sm:px-3 sm:py-1 sm:text-[11px]">
                        <svg class="h-2.5 w-2.5 fill-current sm:h-3 sm:w-3" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        Featured
                    </span>
                @elseif($isNew)
                    <span class="rounded-full bg-[var(--color-primary)] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white shadow-sm sm:px-3 sm:py-1 sm:text-[11px]">
                        New
                    </span>
                @endif
            </div>

            @if($product->cardImage)
                <img src="{{ $product->cardImage->url }}"
                     alt="{{ $product->name }}"
                     loading="lazy"
                     class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-105 {{ $isOutOfStock ? 'opacity-60 grayscale-[0.3]' : '' }}">
            @else
                <div class="flex h-full w-full items-center justify-center bg-gray-100">
                    <span class="text-3xl font-light text-gray-400 sm:text-5xl">
                        {{ substr($product->name, 0, 1) }}
                    </span>
                </div>
            @endif

            <!-- Bottom hover overlay: reveals a "view product" cue without adding a competing CTA -->
            @unless($isOutOfStock)
                <div class="pointer-events-none absolute inset-x-0 bottom-0 flex translate-y-2 items-center justify-center bg-gradient-to-t from-black/55 via-black/10 to-transparent px-3 pb-3 pt-8 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                    <span class="rounded-full bg-white px-3 py-1.5 text-[11px] font-semibold text-gray-900 shadow-sm sm:text-xs">
                        View Product
                    </span>
                </div>
            @endunless
        </div>

        <!-- Product Details -->
        <div class="mt-2 flex flex-col gap-1 sm:mt-3 sm:gap-1.5">

            <!-- Product Title -->
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-gray-900 transition-colors group-hover:text-[var(--color-primary)] sm:text-base">
                {{ $product->name }}
            </h3>

            <!-- Rating -->
            <div class="flex items-center gap-1 sm:gap-1.5">
                @if($product->reviews_count > 0)
                    <div class="flex">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="h-3 w-3 fill-current {{ $i <= round($rating) ? 'text-amber-400' : 'text-gray-300' }} sm:h-4 sm:w-4" viewBox="0 0 20 20">
                                <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                            </svg>
                        @endfor
                    </div>
                    <span class="text-[10px] text-gray-500 sm:text-xs">({{ $product->reviews_count }})</span>
                @else
                    <span class="text-[10px] text-gray-400 sm:text-xs">No ratings yet</span>
                @endif
            </div>

            <!-- Price Display -->
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-bold text-gray-900 sm:text-base">
                    {{ $product->display_price_label }}
                </span>
                @if($isOutOfStock)
                    <span class="text-[10px] font-medium text-gray-400 sm:text-xs">Unavailable</span>
                @endif
            </div>

        </div>

    </a>
</div>