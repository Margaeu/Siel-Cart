@props([
    'product',
    'badge' => null,
    'isBestSeller' => false,
    'isTopPick' => false,
])

@php
    $isNew = $product->created_at?->gt(now()->subDays(7));

    // A product that has earned a Best Seller or Top Pick badge outranks
    // the admin-set Featured tag, so Featured is hidden once either applies
    // -- no card should show "Featured" stacked on top of "Best Seller"/"Top Pick".
    $hasSalesBadge = in_array($badge, ['best_seller', 'top_pick'], true) || $isBestSeller || $isTopPick;
@endphp

<div {{ $attributes->class(['absolute left-2 right-2 top-2 z-10 flex flex-wrap items-start gap-1.5 sm:left-3 sm:right-3 sm:top-3 sm:gap-2']) }}>
    @if($product->stock_status === 'out_of_stock')
        <span class="rounded-full bg-gray-900 px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Sold Out</span>
    @endif

    @if($badge === 'best_seller' || $isBestSeller)
        <span class="inline-flex items-center gap-1 rounded-full bg-amber-600 px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">
            <svg class="h-2.5 w-2.5 fill-current sm:h-3 sm:w-3" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M10 17.5s-6.5-4.06-8.5-7.86C.36 7.26 1.2 4.5 3.75 3.7c1.7-.53 3.45.14 4.5 1.5.3.4.55.83.75 1.3.2-.47.45-.9.75-1.3 1.05-1.36 2.8-2.03 4.5-1.5C16.8 4.5 17.64 7.26 18.5 9.64c-2 3.8-8.5 7.86-8.5 7.86z"/>
            </svg>
            Best Seller
        </span>
    @endif

    @if($badge === 'top_pick' || $isTopPick)
               <span class="inline-flex items-center gap-1 rounded-full bg-amber-600 px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">
            <svg class="h-2.5 w-2.5 fill-current sm:h-3 sm:w-3" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M10 17.5s-6.5-4.06-8.5-7.86C.36 7.26 1.2 4.5 3.75 3.7c1.7-.53 3.45.14 4.5 1.5.3.4.55.83.75 1.3.2-.47.45-.9.75-1.3 1.05-1.36 2.8-2.03 4.5-1.5C16.8 4.5 17.64 7.26 18.5 9.64c-2 3.8-8.5 7.86-8.5 7.86z"/>
            </svg>
            Top Pick
        </span>
        @endif

    @if($product->is_featured && ! $hasSalesBadge)
        <span class="rounded-full bg-[var(--color-secondary)] px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-gray-900 shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Featured</span>
    @endif

    @if($isNew)
        <span class="rounded-full bg-[var(--color-primary)] px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">New</span>
    @endif
</div>