<a
    href="{{ route('cart.index') }}"
    class="group relative inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]"
    aria-label="Cart{{ $cartCount > 0 ? ' — '.$cartCount.' '.Str::plural('item', $cartCount) : '' }}"
    title="Cart"
>
    <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
    @if ($cartCount > 0)
        <span
            class="absolute right-0 top-0 flex min-w-5 -translate-y-1/4 translate-x-1/4 items-center justify-center rounded-full bg-[var(--color-secondary)] px-1 text-[0.65rem] font-bold leading-5 text-gray-950 ring-2 ring-[var(--color-primary)]">
            {{ $cartCount }}
        </span>
    @endif
</a>
