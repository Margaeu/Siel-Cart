{{--
    Product Card Component
    Full-bleed image tile with overlay badges, then name, rating and price
    sitting directly on the page background (no white card box).
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
        <div class="relative aspect-[4/5] w-full overflow-hidden rounded-2xl bg-gray-100">

            <!-- Status Badges (Top-Left) -->
            <div class="absolute top-3 left-3 z-10 flex flex-col items-start gap-2">
                @if($isOutOfStock)
                    <span class="rounded-full bg-gray-900/85 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow-sm">
                        Sold Out
                    </span>
                @elseif($product->is_featured)
                    <span class="rounded-full bg-[var(--color-secondary)] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-[var(--color-primary)] shadow-sm">
                        Featured
                    </span>
                @elseif($isNew)
                    <span class="rounded-full bg-[var(--color-primary)] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow-sm">
                        New
                    </span>
                @endif
            </div>

            @if($product->cardImage)
                <img src="{{ $product->cardImage->url }}"
                     alt="{{ $product->name }}"
                     loading="lazy"
                     class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-105 {{ $isOutOfStock ? 'opacity-75' : '' }}">
            @else
                <div class="flex h-full w-full items-center justify-center bg-gray-100">
                    <span class="text-5xl font-light text-gray-400">
                        {{ substr($product->name, 0, 1) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Product Details -->
        <div class="mt-3 flex flex-col gap-1.5">

            <!-- Product Title -->
            <h3 class="line-clamp-2 text-base font-semibold leading-snug text-gray-900 transition-colors group-hover:text-[var(--color-primary)]">
                {{ $product->name }}
            </h3>

            <!-- Rating -->
            <div class="flex items-center gap-1.5">
                <div class="flex">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="h-4 w-4 fill-current {{ $i <= round($rating) ? 'text-amber-400' : 'text-gray-300' }}" viewBox="0 0 20 20">
                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                        </svg>
                    @endfor
                </div>
                <span class="text-xs text-gray-500">{{ $product->reviews_count }}</span>
            </div>

            <!-- Price Display -->
            <div class="text-base font-bold text-gray-900">
                {{ $product->display_price_label }}
            </div>

        </div>

    </a>
</div>
