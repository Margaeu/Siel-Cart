@php
    // Same source of truth as the storefront masthead
    // (components/layouts/front-end-layout.blade.php) - keeps the auth pages
    // showing the same name/tagline/theme color as the rest of the site.
    $siteName = config('app.name', 'Siel Cart');
    $siteTagline = 'The CLSU Campus Store';
@endphp

<header class="w-full bg-[var(--color-primary)] text-white shadow-sm">
    {{-- Markup mirrors the storefront masthead's brand block exactly
         (components/layouts/front-end-layout.blade.php) - down to the gap,
         divider and text sizes - so navigating between the storefront and
         the auth pages doesn't show a header that shifts or re-flows. --}}
    <div class="mx-auto flex min-h-[5.5rem] w-full max-w-[87.5rem] items-center px-4 sm:min-h-[6.5rem] sm:px-6 md:min-h-[7rem] md:px-8 lg:min-h-[9rem] lg:px-10 xl:min-h-[10rem]">
        <a
            href="{{ route('home') }}"
            class="group flex min-w-0 shrink-0 items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[var(--color-primary)]"
            aria-label="{{ $siteName }} home"
        >
            <img
                src="{{ asset('images/LOGO.png') }}"
                alt="CLSU seal"
                class="size-16 shrink-0 object-contain transition-transform duration-200 group-hover:scale-[1.03] sm:size-20 md:size-[5.5rem] lg:size-28 xl:size-32"
            >
            <span class="hidden h-14 w-px shrink-0 bg-white/40 sm:block sm:h-18 md:h-20 lg:h-28 xl:h-32" aria-hidden="true"></span>
            <span class="min-w-0 pl-2">
                <span class="block truncate text-base font-bold leading-tight tracking-[-0.02em] sm:text-xl md:text-2xl lg:text-[2.75rem] xl:text-[3.25rem]">
                    {{ $siteName }}
                </span>
                <span class="mt-0.5 hidden truncate text-xs font-medium text-white/75 sm:block lg:text-base xl:text-lg">
                    {{ $siteTagline }}
                </span>
            </span>
        </a>
    </div>
</header>
