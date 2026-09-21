{{--
    Account sidebar shared by the dashboard, orders list, and profile page,
    so they can't drift apart. Pass $active ('overview' | 'orders' |
    'profile') to mark the current page.
--}}
@php
    $customer = auth('customer')->user();
    $active = $active ?? 'overview';

    $activeClasses = 'bg-emerald-50 text-[var(--color-primary)] font-semibold border-l-4 border-[var(--color-primary)]';
    $idleClasses = 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-900';
@endphp

<div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
    {{-- User Mini Profile --}}
    <div class="flex items-center gap-3.5 pb-6 border-b border-gray-100">
        <div class="w-12 h-12 rounded-full bg-[var(--color-primary)] text-white font-bold flex items-center justify-center text-sm tracking-wide shrink-0">
            {{ strtoupper(substr($customer->first_name ?? 'U', 0, 1) . substr($customer->last_name ?? '', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <h3 class="font-bold text-gray-900 leading-tight truncate">
                {{ $customer->first_name }} {{ $customer->last_name }}
            </h3>
            <a href="{{ route('customer.profile') }}" class="text-xs text-gray-500 hover:text-[var(--color-primary)] transition">
                Edit profile
            </a>
        </div>
    </div>

    {{-- Navigation Links --}}
    <nav class="mt-6 space-y-1">
        {{-- Overview --}}
        <a href="{{ route('customer.dashboard') }}"
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition {{ $active === 'overview' ? $activeClasses : $idleClasses }}">
            <svg class="w-4 h-4 shrink-0 {{ $active === 'overview' ? '' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span>Overview</span>
        </a>

        {{-- My Orders --}}
        <a href="{{ route('customer.orders') }}"
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition {{ $active === 'orders' ? $activeClasses : $idleClasses }}">
            <svg class="w-4 h-4 shrink-0 {{ $active === 'orders' ? '' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <span>My Orders</span>
        </a>

        {{--
            Profile and Password are two tabs of the same page, so on that page
            the highlight follows the profile page's Alpine `tab` rather than
            being fixed server-side.
        --}}
        {{-- Profile --}}
        @if($active === 'profile')
            <a href="{{ route('customer.profile') }}"
               x-on:click.prevent="tab = 'profile'; history.replaceState(null, '', location.pathname)"
               :class="tab === 'profile' ? @js($activeClasses) : @js($idleClasses)"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition">
                <svg class="w-4 h-4 shrink-0" :class="tab === 'profile' ? '' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        @else
            <a href="{{ route('customer.profile') }}"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition {{ $idleClasses }}">
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        @endif
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span>Profile</span>
        </a>

        {{-- Password --}}
        @if($active === 'profile')
            <a href="{{ route('customer.profile') }}#password"
               x-on:click.prevent="tab = 'security'; history.replaceState(null, '', '#password')"
               :class="tab === 'security' ? @js($activeClasses) : @js($idleClasses)"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition">
                <svg class="w-4 h-4 shrink-0" :class="tab === 'security' ? '' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        @else
            <a href="{{ route('customer.profile') }}#password"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition {{ $idleClasses }}">
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        @endif
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span>Password</span>
        </a>

        <div class="pt-4 mt-4 border-t border-gray-100 space-y-1">
            {{-- Continue Shopping --}}
            <a href="{{ route('products.index') }}"
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition {{ $idleClasses }}">
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <span>Continue shopping</span>
            </a>

            {{-- Log out --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 transition text-left">
                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </nav>
</div>
