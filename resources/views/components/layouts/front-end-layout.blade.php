<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'E-Commerce Store') }}</title>

    @include('partials.theme-styles')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
    <style>
            [x-cloak] {
                display: none !important;
            }
    </style>

        @filamentStyles

</head>
<body class="min-h-screen flex flex-col bg-gray-50 antialiased">
    @php
        $siteName = \App\Models\Setting::get('site_name') ?: config('app.name', 'GreenCobraCart');
        $siteTagline = \App\Models\Setting::get('tagline') ?: 'THE OFFICIAL CLSU MERCHANDISE STORE';
        $desktopNavLink = 'relative flex h-16 items-center px-0.5 text-[0.95rem] font-semibold tracking-[-0.01em] transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] focus-visible:ring-offset-4';
        $mobileNavLink = 'flex min-h-11 items-center rounded-md px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]';
    @endphp

    <!-- Storefront masthead -->
    <header
        class="sticky top-0 z-50 bg-white shadow-[0_1px_12px_rgba(15,23,42,0.08)]"
        x-data="{ navigationOpen: false }"
        x-on:keydown.escape.window="navigationOpen = false"
    >
        <div class="bg-[var(--color-primary)] text-white">
            <div class="mx-auto flex min-h-[5rem] max-w-[90rem] items-center justify-between gap-4 px-4 sm:min-h-24 sm:px-7 lg:px-11">
                <a
                    href="{{ route('home') }}"
                    class="group flex min-w-0 items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] focus-visible:ring-offset-4 focus-visible:ring-offset-[var(--color-primary)] sm:gap-5"
                    aria-label="{{ $siteName }} home"
                >
                    <img
                        src="{{ asset('images/clsu_logo_white.png') }}"
                        alt="CLSU seal"
                        class="size-12 shrink-0 object-contain transition-transform duration-200 group-hover:scale-[1.03] sm:size-[4.25rem]"
                    >
                    <span class="hidden h-14 w-px shrink-0 bg-[var(--color-secondary)] sm:block" aria-hidden="true"></span>
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-bold leading-tight tracking-[-0.035em] sm:text-[1.75rem]">
                            {{ $siteName }}
                        </span>
                        <span class="mt-1 hidden truncate text-[0.7rem] font-medium tracking-[0.22em] text-[var(--color-secondary)] sm:block lg:text-[0.78rem]">
                            {{ Str::upper($siteTagline) }}
                        </span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                    @auth('customer')
                        <a
                            href="{{ route('customer.dashboard') }}"
                            class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]"
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
                            class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]"
                            aria-label="Log in"
                            title="Log in"
                        >
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.5 20.25a7.5 7.5 0 0115 0" />
                            </svg>
                        </a>
                    @endauth

                    <livewire:cart-icon />

                    <button
                        type="button"
                        class="inline-flex size-11 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] md:hidden"
                        x-on:click="navigationOpen = !navigationOpen"
                        x-bind:aria-expanded="navigationOpen"
                        aria-controls="mobile-store-navigation"
                        aria-label="Toggle navigation"
                    >
                        <svg x-show="!navigationOpen" class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                        <svg x-cloak x-show="navigationOpen" class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop store navigation -->
        <nav class="hidden bg-white md:block" aria-label="Store navigation">
            <div class="mx-auto max-w-[90rem] px-7 lg:px-11">
                <ul class="flex items-center gap-8 lg:gap-10">
                    <li>
                        <a
                            href="{{ route('home') }}"
                            @class([
                                $desktopNavLink,
                                'text-[var(--color-primary)] after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('home'),
                                'text-gray-700 hover:text-[var(--color-primary)]' => ! request()->routeIs('home'),
                            ])
                            @if(request()->routeIs('home')) aria-current="page" @endif
                        >Home</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('products.index') }}"
                            @class([
                                $desktopNavLink,
                                'text-[var(--color-primary)] after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('products.*'),
                                'text-gray-700 hover:text-[var(--color-primary)]' => ! request()->routeIs('products.*'),
                            ])
                            @if(request()->routeIs('products.*')) aria-current="page" @endif
                        >Shop</a>
                    </li>
                    <li>
                        <a
                            href="{{ route('about') }}"
                            @class([
                                $desktopNavLink,
                                'text-[var(--color-primary)] after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:rounded-t-full after:bg-[var(--color-secondary)]' => request()->routeIs('about'),
                                'text-gray-700 hover:text-[var(--color-primary)]' => ! request()->routeIs('about'),
                            ])
                            @if(request()->routeIs('about')) aria-current="page" @endif
                        >About Us</a>
                    </li>
                </ul>
            </div>
        </nav>

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
                    >Shop</a>
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
    <footer class="bg-gray-800 text-white mt-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-bold mb-4">{{ $siteName }}</h3>
                    <p class="text-gray-400">Your one-stop shop for quality products.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('products.index') }}" class="text-gray-400 hover:text-white">Shop</a></li>
                        <li><a href="{{ route('about') }}" class="text-gray-400 hover:text-white">About Us</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Return/Refund Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">My Account</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('customer.dashboard') }}" class="text-gray-400 hover:text-white">Dashboard</a></li>
                        <li><a href="{{ route('customer.orders') }}" class="text-gray-400 hover:text-white">Orders</a></li>
                        <li><a href="{{ route('customer.profile') }}" class="text-gray-400 hover:text-white">Profile</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
    @filamentScripts
    
</body>
</html>
