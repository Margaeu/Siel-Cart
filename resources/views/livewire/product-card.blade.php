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
    $reviewsCount = $product->reviews_count;
@endphp

<div class="group relative flex h-full flex-col">
    <a
        href="{{ route('products.show', $product->slug) }}"
        class="flex h-full flex-col rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2"
    >

        <!-- Product Image Tile -->
        <div class="relative aspect-[4/5] w-full overflow-hidden rounded-2xl bg-gray-100">

            <!-- Status Badges (Top-Left) -->
            {{--
                Badges hold a 10px floor and solid fills rather than shrinking
                with the card: at two columns on a 360px phone the old 9px
                type on a translucent fill was the first thing to become
                unreadable.
            --}}
            <div class="absolute left-2 top-2 z-10 flex flex-col items-start gap-1.5 sm:left-3 sm:top-3 sm:gap-2">
                @if($isOutOfStock)
                    <span class="rounded-full bg-gray-900 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[11px]">
                        Sold Out
                    </span>
                @elseif($product->is_featured)
                    <span class="rounded-full bg-[var(--color-secondary)] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-900 shadow-sm sm:px-2.5 sm:text-[11px]">
                        Featured
                    </span>
                @elseif($isNew)
                    <span class="rounded-full bg-[var(--color-primary)] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[11px]">
                        New
                    </span>
                @endif
            </div>

            @if($product->cardImage)
                <img src="{{ $product->cardImage->url }}"
                     alt="{{ $product->name }}"
                     loading="lazy"
                     class="h-full w-full object-cover transition duration-500 ease-out motion-safe:group-hover:scale-105 {{ $isOutOfStock ? 'opacity-75' : '' }}">
            @else
                <div class="flex h-full w-full items-center justify-center bg-gray-100">
                    <span class="text-3xl font-light text-gray-400 sm:text-5xl">
                        {{ substr($product->name, 0, 1) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Product Details -->
        <div class="mt-2.5 flex flex-1 flex-col gap-1 sm:mt-3 sm:gap-1.5">

            <!-- Product Title -->
            {{--
                The name takes only the height it needs -- a one-line name used
                to reserve a second line anyway, which opened a visible hole
                between the name and the rating row. Prices still land on one
                baseline across a row because the price below carries mt-auto,
                so the slack a short name leaves collects above the price
                instead of under the name.
            --}}
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-gray-900 transition-colors group-hover:text-[var(--color-primary)] sm:text-base">
                {{ $product->name }}
            </h3>

            <!--
                Rating. Shown on every card, including products with no reviews
                yet, which read as five empty stars beside a zero -- the store
                wants the row to stay put rather than appear only once a product
                has been reviewed.

                The row is always in the layout, so it costs no alignment: every
                card in a row keeps the same height and the prices stay on one
                baseline either way.
            -->
            <div class="flex items-center gap-1 sm:gap-1.5">
                <div
                    class="flex"
                    role="img"
                    aria-label="{{ $reviewsCount > 0
                        ? 'Rated ' . round($rating, 1) . ' out of 5, ' . $reviewsCount . ' ' . Str::plural('review', $reviewsCount)
                        : 'No reviews yet' }}"
                >
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="h-3.5 w-3.5 fill-current {{ $i <= round($rating) ? 'text-amber-500' : 'text-gray-300' }} sm:h-4 sm:w-4" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                        </svg>
                    @endfor
                </div>
                <span class="text-xs text-gray-600 sm:text-sm" aria-hidden="true">{{ $reviewsCount }}</span>
            </div>

            <!-- Price Display -->
            <div class="mt-auto pt-0.5 text-[15px] font-bold text-gray-900 sm:text-base">
                {{ $product->display_price_label }}
            </div>

        </div>

    </a>
</div>
