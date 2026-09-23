@php
    // Same source of truth as the storefront header
    // (components/layouts/front-end-layout.blade.php) - keeps the login and
    // register pages showing the same name/tagline as the rest of the site.
    $siteName = config('app.name', 'Siel Cart');
    $siteTagline = 'The CLSU Campus Store';
@endphp

<a
    href="{{ route('home') }}"
    class="group inline-flex min-w-0 items-center gap-4 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#557F13] focus-visible:ring-offset-4 focus-visible:ring-offset-white"
    aria-label="{{ $siteName }} home"
>
    <img
        src="{{ asset('images/Logo_Black.png') }}"
        alt="Central Luzon State University seal"
        class="size-20 shrink-0 object-contain drop-shadow-sm transition-transform duration-200 group-hover:scale-[1.03] sm:size-24"
        width="96"
        height="96"
    >
    <span class="min-w-0 text-left">
        <span class="block truncate text-2xl font-bold leading-tight tracking-[-0.02em] text-[#557F13] sm:text-3xl">
            {{ Str::upper($siteName) }}
        </span>
        <span class="mt-1 block truncate text-sm text-gray-500 sm:text-base">
            {{ $siteTagline }}
        </span>
    </span>
</a>