@props([
    'product',
    'badge' => null,
    'isBestSeller' => false,
    'isTopPick' => false,
])

@php
    $isNew = $product->created_at?->gt(now()->subDays(7));
@endphp

<div {{ $attributes->class(['absolute left-2 right-2 top-2 z-10 flex flex-wrap items-start gap-1.5 sm:left-3 sm:right-3 sm:top-3 sm:gap-2']) }}>
    @if($product->stock_status === 'out_of_stock')
        <span class="rounded-full bg-gray-900 px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Sold Out</span>
    @endif

    @if($badge === 'best_seller' || $isBestSeller)
        <span class="rounded-full bg-amber-600 px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Best Seller</span>
    @endif

    @if($badge === 'top_pick' || $isTopPick)
        <span class="rounded-full bg-[var(--color-primary)] px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Top Pick</span>
    @endif

    @if($product->is_featured)
        <span class="rounded-full bg-[var(--color-secondary)] px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-gray-900 shadow-sm sm:px-2.5 sm:text-[0.6875rem]">Featured</span>
    @endif

    @if($isNew)
        <span class="rounded-full bg-[var(--color-primary)] px-2 py-1 text-[0.625rem] font-bold uppercase tracking-wide text-white shadow-sm sm:px-2.5 sm:text-[0.6875rem]">New</span>
    @endif
</div>
