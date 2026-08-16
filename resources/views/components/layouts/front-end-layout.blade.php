<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'E-Commerce Store') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

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
<body class="bg-gray-50 antialiased">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Top Bar -->
            <div class="flex items-center justify-between py-4">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="text-2xl font-bold text-blue-600">
                        {{ config('app.name', 'E-Commerce') }}
                    </a>
                </div>

                <!-- Search Bar (Desktop) -->
                <div class="hidden flex-1 mx-8 lg:block">
                    <livewire:search-bar />
                </div>

                <!-- Right Side -->
                <div class="flex items-center gap-4">
                    @auth('customer')
                        <a href="{{ route('customer.dashboard') }}" class="text-gray-700 hover:text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-blue-600">
                            Login
                        </a>
                    @endauth

                    <!-- Cart -->
                    <livewire:cart-icon />
                </div>
            </div>

            <!-- Navigation -->
            <nav class="border-t py-4">
                <ul class="flex items-center gap-8">
                    <li>
                        <a href="{{ route('home') }}" class="text-gray-700 hover:text-blue-600 font-medium">
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-blue-600 font-medium">
                            Shop
                        </a>
                    </li>
                    @foreach(\App\Models\Category::active()->sorted()->limit(5)->get() as $category)
                        <li>
                            <a href="{{ route('products.index', ['category' => $category->slug]) }}" 
                               class="text-gray-700 hover:text-blue-600">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        {{ $slot }}
    </main>
    @livewire('notifications')


    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-bold mb-4">{{ config('app.name') }}</h3>
                    <p class="text-gray-400">Your one-stop shop for quality products.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('products.index') }}" class="text-gray-400 hover:text-white">Shop</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Shipping Info</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Returns</a></li>
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
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
    @filamentScripts
    
</body>
</html>