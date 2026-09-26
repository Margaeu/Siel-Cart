<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'SIEL CART') }}</title>

    @include('partials.theme-styles')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
    <style>
            [x-cloak] {
                display: none !important;
            }
    </style>

</head>
<body class="min-h-screen flex flex-col bg-gray-50 antialiased">
    @php
        $siteName = config('app.name', 'SIEL CART');
        $siteTagline = 'The CLSU Campus Store';
        $desktopNavLink = 'relative flex h-[4.75rem] items-center px-0.5 text-[0.95rem] font-semibold tracking-[-0.01em] transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[var(--color-primary)]';
        $mobileNavLink = 'flex min-h-11 items-center rounded-md px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]';
    @endphp

    <!-- Storefront masthead: logo, brand, nav links and actions all in a single row -->
    <header
        class="sticky top-0 z-50 border-b border-black/10 bg-[var(--color-primary)] text-white shadow-sm"
        x-data="{ navigationOpen: false, mobileSearchOpen: false }"
        x-on:keydown.escape.window="navigationOpen = false; mobileSearchOpen = false"
    >
        <div class="mx-auto flex min-h-14 w-full max-w-[87.5rem] items-center justify-between gap-2 px-4 sm:min-h-16 sm:gap-4 sm:px-6 md:min-h-[4.75rem] md:px-8 lg:px-10">
            <!-- Brand -->
            <a
                href="{{ route('home') }}"
                class="group flex min-w-0 shrink-0 items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[var(--color-primary)]"
                aria-label="{{ $siteName }} home"
            >
                <img
                    src="{{ asset('images/LOGO.png') }}"
                    alt="CLSU seal"
                    class="size-8 shrink-0 object-contain transition-transform duration-200 group-hover:scale-[1.03] sm:size-10 md:size-11 lg:size-12"
                >
                <span class="hidden h-8 w-px shrink-0 bg-white/25 sm:block" aria-hidden="true"></span>
                <span class="min-w-0 pl-2">
                    <span class="block truncate text-sm font-bold leading-tight tracking-[-0.02em] sm:text-lg md:text-xl">
                        {{ $siteName }}
                    </span>
                    <span class="mt-0.5 hidden truncate text-xs font-medium text-white/75 sm:block">
                        {{ $siteTagline }}
                    </span>
                </span>
            </a>

            <!-- Desktop navigation + actions, in the same row as the brand -->
            <div class="hidden min-w-0 items-center gap-6 md:flex lg:gap-9">
                <nav aria-label="Store navigation">
                    <ul class="flex items-center gap-6 lg:gap-9">
                        <li>
                            <a
                                href="{{ route('home') }}"
                                @class([
                                    $desktopNavLink,
                                    'text-white after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('home'),
                                    'text-white/75 hover:text-white' => ! request()->routeIs('home'),
                                ])
                                @if(request()->routeIs('home')) aria-current="page" @endif
                            >Home</a>
                        </li>
                        <li>
                            <a
                                href="{{ route('products.index') }}"
                                @class([
                                    $desktopNavLink,
                                    'text-white after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('products.*'),
                                    'text-white/75 hover:text-white' => ! request()->routeIs('products.*'),
                                ])
                                @if(request()->routeIs('products.*')) aria-current="page" @endif
                            >Products</a>
                        </li>
                        <li>
                            <a
                                href="{{ route('about') }}"
                                @class([
                                    $desktopNavLink,
                                    'text-white after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('about'),
                                    'text-white/75 hover:text-white' => ! request()->routeIs('about'),
                                ])
                                @if(request()->routeIs('about')) aria-current="page" @endif
                            >About Us</a>
                        </li>
                    </ul>
                </nav>

                <form
                    action="{{ route('products.index') }}"
                    method="GET"
                    class="min-w-0 max-w-[15rem] flex-1 lg:max-w-xs"
                    role="search"
                    aria-label="Search products"
                >
                    <label for="desktop-product-search" class="sr-only">Search products</label>
                    <div class="group relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-white/60 transition-colors group-focus-within:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35M18.5 10.5a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                        </svg>
                        <input
                            id="desktop-product-search"
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search products…"
                            class="h-10 w-full rounded-full border border-white/25 bg-white/10 pl-9 pr-3 text-sm text-white placeholder:text-white/60 transition-colors focus:border-white/50 focus:bg-white focus:text-gray-900 focus:placeholder:text-gray-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        >
                    </div>
                </form>

                <div class="-ml-4 flex shrink-0 items-center gap-1 lg:-ml-7">
                    @auth('customer')
                        <a
                            href="{{ route('customer.dashboard') }}"
                            class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="My account"
                            title="My account"
                        >
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.5 20.25a7.5 7.5 0 0115 0" />
                            </svg>
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="Log in"
                            title="Log in"
                        >
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.5 20.25a7.5 7.5 0 0115 0" />
                            </svg>
                        </a>
                    @endauth

                    <livewire:cart-icon />
                </div>
            </div>

            <!-- Mobile: search toggle + cart + nav toggle -->
            <div class="flex shrink-0 items-center gap-0.5 sm:gap-1 md:hidden">
                <button
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    x-on:click="mobileSearchOpen = !mobileSearchOpen; navigationOpen = false"
                    x-bind:aria-expanded="mobileSearchOpen"
                    aria-controls="mobile-product-search"
                    aria-label="Toggle search"
                >
                    <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35M18.5 10.5a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                    </svg>
                </button>

                <livewire:cart-icon />

                <button
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    x-on:click="navigationOpen = !navigationOpen; mobileSearchOpen = false"
                    x-bind:aria-expanded="navigationOpen"
                    aria-controls="mobile-store-navigation"
                    aria-label="Toggle navigation"
                >
                    <svg x-show="!navigationOpen" class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg x-cloak x-show="navigationOpen" class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile search bar -->
        <div
            id="mobile-product-search"
            x-cloak
            x-show="mobileSearchOpen"
            x-transition.opacity.duration.150ms
            x-effect="if (mobileSearchOpen) $nextTick(() => $refs.mobileSearchInput.focus())"
            class="border-t border-white/15 px-4 py-3 md:hidden"
        >
            <form action="{{ route('products.index') }}" method="GET" role="search" aria-label="Search products">
                <label for="mobile-product-search-input" class="sr-only">Search products</label>
                <div class="group relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-white/60 transition-colors group-focus-within:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35M18.5 10.5a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                    </svg>
                    <input
                        id="mobile-product-search-input"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search products…"
                        x-ref="mobileSearchInput"
                        x-on:keydown.escape.stop="mobileSearchOpen = false"
                        class="h-11 w-full rounded-full border border-white/25 bg-white/10 pl-9 pr-3 text-sm text-white placeholder:text-white/60 transition-colors focus:border-white/50 focus:bg-white focus:text-gray-900 focus:placeholder:text-gray-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    >
                </div>
            </form>
        </div>

        <!-- Mobile store navigation -->
        <nav
            id="mobile-store-navigation"
            x-cloak
            x-show="navigationOpen"
            x-transition.opacity.duration.150ms
            class="border-t border-gray-100 bg-white px-4 py-3 shadow-lg md:hidden"
            aria-label="Mobile store navigation"
        >
            <ul class="space-y-1">
                <li>
                    <a
                        href="{{ route('home') }}"
                        @class([
                            $mobileNavLink,
                            'bg-[color-mix(in_srgb,var(--color-primary)_10%,white)] text-[var(--color-primary)]' => request()->routeIs('home'),
                            'text-gray-700 hover:bg-gray-50 hover:text-[var(--color-primary)]' => ! request()->routeIs('home'),
                        ])
                        @if(request()->routeIs('home')) aria-current="page" @endif
                    >Home</a>
                </li>
                <li>
                    <a
                        href="{{ route('products.index') }}"
                        @class([
                            $mobileNavLink,
                            'bg-[color-mix(in_srgb,var(--color-primary)_10%,white)] text-[var(--color-primary)]' => request()->routeIs('products.*'),
                            'text-gray-700 hover:bg-gray-50 hover:text-[var(--color-primary)]' => ! request()->routeIs('products.*'),
                        ])
                        @if(request()->routeIs('products.*')) aria-current="page" @endif
                    >Products</a>
                </li>
                <li>
                    <a
                        href="{{ route('about') }}"
                        @class([
                            $mobileNavLink,
                            'bg-[color-mix(in_srgb,var(--color-primary)_10%,white)] text-[var(--color-primary)]' => request()->routeIs('about'),
                            'text-gray-700 hover:bg-gray-50 hover:text-[var(--color-primary)]' => ! request()->routeIs('about'),
                        ])
                        @if(request()->routeIs('about')) aria-current="page" @endif
                    >About Us</a>
                </li>
                <li>
                    @auth('customer')
                        <a href="{{ route('customer.dashboard') }}" class="{{ $mobileNavLink }} text-gray-700 hover:bg-gray-50 hover:text-[var(--color-primary)]">My Account</a>
                    @else
                        <a href="{{ route('login') }}" class="{{ $mobileNavLink }} text-gray-700 hover:bg-gray-50 hover:text-[var(--color-primary)]">Log In</a>
                    @endauth
                </li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-cart-toast />
    @livewire('notifications')


    <!-- Footer -->
    {{--
        Type and spacing here are fluid (clamp()) rather than fixed steps: the
        footer is the one block that renders identically on a 360px phone and a
        1440px monitor, and static px sizes made it cramped on one and oversized
        on the other. The sizes live in a scoped style block instead of Tailwind
        arbitrary values so the footer is not dependent on a Vite rebuild to keep
        its proportions.
    --}}
    <style>
        .site-footer {
            --footer-logo: clamp(3.5rem, 2.25rem + 5vw, 6.5rem);
            --footer-brand: clamp(1.15rem, 0.95rem + 0.9vw, 1.9rem);
            --footer-heading: clamp(0.95rem, 0.88rem + 0.28vw, 1.15rem);
            --footer-body: clamp(0.875rem, 0.83rem + 0.22vw, 1rem);
            --footer-meta: clamp(0.78rem, 0.75rem + 0.16vw, 0.9rem);
            --footer-gap: clamp(1.75rem, 1rem + 2.5vw, 3.5rem);
            --footer-pad-y: clamp(2.5rem, 1.75rem + 3vw, 4.5rem);
        }

        .site-footer__logo {
            width: var(--footer-logo);
            height: var(--footer-logo);
        }

        .site-footer__brand { font-size: var(--footer-brand); }
        .site-footer__heading { font-size: var(--footer-heading); }
        .site-footer__text { font-size: var(--footer-body); }
        .site-footer__meta { font-size: var(--footer-meta); }
    </style>

    <footer class="site-footer mt-16 bg-gray-800 text-white">
        <div
            class="mx-auto w-full max-w-[87.5rem] px-5 sm:px-8 lg:px-10"
            style="padding-top: var(--footer-pad-y); padding-bottom: clamp(1.5rem, 1rem + 1.5vw, 2.5rem);"
        >
            {{--
                Brand block sits beside the link columns on wide screens and takes
                a full row above them on narrower ones. The link groups never fall
                back to a single stacked column: phones get two columns (My Account
                wraps under Quick Links), and from `sm` all three share one row.
            --}}
            <div
                class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-[minmax(0,2.2fr)_repeat(3,minmax(0,1fr))]"
                style="gap: var(--footer-gap);"
            >
                <div class="col-span-2 sm:col-span-3 lg:col-span-1">
                    {{--
                        Logo and contact details share one row: the seal sits to the
                        left of the whole text stack (name, tagline, address, email)
                        rather than only the name, so the block reads as one unit.
                    --}}
                    <div class="flex items-center gap-4 sm:gap-5">
                        <img
                            src="{{ asset('images/LOGO.png') }}"
                            alt="{{ $siteName }} logo"
                            class="site-footer__logo shrink-0 object-contain"
                        >

                        <div class="min-w-0">
                            <p class="site-footer__brand font-bold leading-tight tracking-[-0.02em]">{{ $siteName }}</p>

                            <!-- Replaces the old generic tagline: the office people actually walk to and write to -->
                            <ul class="site-footer__text mt-3 space-y-2.5 text-gray-400">
                                <li class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 size-5 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0Z" />
                                    </svg>
                                    <span class="min-w-0">UBAP Office, Central Luzon State University, Science City of Muñoz, Nueva Ecija, Philippines 3120</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 size-5 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 9.16a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    <a
                                        class="min-w-0 break-all transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]"
                                    >ubap@clsu.edu.ph</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="site-footer__heading font-semibold text-white">Quick Links</h4>
                    <ul class="site-footer__text mt-4 space-y-2.5">
                        <li><a href="{{ route('products.index') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Products</a></li>
                        <li><a href="{{ route('about') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">About Us</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="site-footer__heading font-semibold text-white">Customer Service</h4>
                    <ul class="site-footer__text mt-4 space-y-2.5">
                        <li><a href="{{ route('privacy-policy') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Privacy Policy</a></li>
                        <li><a href="{{ route('terms-and-conditions') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Terms and Conditions</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="site-footer__heading font-semibold text-white">My Account</h4>
                    <ul class="site-footer__text mt-4 space-y-2.5">
                        <li><a href="{{ route('customer.dashboard') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Dashboard</a></li>
                        <li><a href="{{ route('customer.orders') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Orders</a></li>
                        <li><a href="{{ route('customer.profile') }}" class="inline-block text-gray-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">Profile</a></li>
                    </ul>
                </div>
            </div>

            <div
                class="site-footer__meta border-t border-white/10 text-center text-gray-400 sm:text-left"
                style="margin-top: var(--footer-gap); padding-top: clamp(1.25rem, 1rem + 1vw, 2rem);"
            >
                <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @include('partials.chat-widget')

    @livewireScripts

</body>
</html>
