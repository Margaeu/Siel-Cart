{{-- Uses the parent carousel's scroll state and scrollByCard action. --}}
<div x-show="canScrollLeft || canScrollRight" x-cloak class="mb-4 flex items-center justify-between gap-3 sm:hidden">
    <span class="text-xs font-medium text-gray-500">Swipe to explore</span>
    <div class="flex gap-2">
        <button type="button" x-on:click="scrollByCard(-1)" x-bind:disabled="!canScrollLeft"
                class="flex size-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                aria-label="Scroll to previous products">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        <button type="button" x-on:click="scrollByCard(1)" x-bind:disabled="!canScrollRight"
                class="flex size-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                aria-label="Scroll to next products">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
</div>
