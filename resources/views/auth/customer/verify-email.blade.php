<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - SIEL CART</title>

    {{-- Tailwind & Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @include('partials.theme-styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-50 text-gray-800 font-sans antialiased flex flex-col">

    <x-customer-auth-header />

    <div class="flex flex-1 items-center justify-center p-4">

    {{-- Main Auth Card --}}
    <div class="w-full max-w-[26.25rem] bg-white rounded-3xl p-8 sm:p-10 shadow-[0_10px_35px_-5px_rgba(0,0,0,0.06)] border border-gray-100">

        {{-- Card Title & Subtitle --}}
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 leading-snug">
                Verify your email
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                One last step before you can start shopping.
            </p>
        </div>

        {{-- Status / Info Alert Messages --}}
        @if (session('verify_reason'))
            <div class="mb-5 rounded-xl bg-amber-50 border border-amber-200/60 p-3 text-xs text-amber-900 flex items-start gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('verify_reason') }}</span>
            </div>
        @endif

        @if (session('status') === 'verification-link-sent')
            <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200/60 p-3 text-xs text-emerald-800 flex items-start gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>A fresh verification link has been sent to your email address.</span>
            </div>
        @endif

        {{-- Envelope Icon Container --}}
        <div class="flex justify-center mb-5">
            <div class="w-14 h-14 rounded-2xl bg-[var(--color-primary)]/10 text-[var(--color-primary)] flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
        </div>

        {{-- Instructional Body --}}
        <div class="text-center text-xs text-gray-500 space-y-1 mb-6">
            <p>We sent a verification link to</p>
            <p class="font-bold text-gray-900 break-all text-sm">
                {{ auth('customer')->user()->email }}
            </p>
            <p class="pt-2 leading-relaxed">
                Click the link in that email to activate your account. If you haven't received it, check your spam folder or request a new one below.
            </p>
        </div>

        {{-- Actions --}}
        <div class="space-y-2.5">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button 
                    type="submit" 
                    class="w-full py-2.5 px-4 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] active:brightness-90 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span>Resend Verification Email</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button 
                    type="submit" 
                    class="w-full py-2.5 px-4 bg-transparent hover:bg-gray-50 text-gray-600 text-xs sm:text-sm font-semibold rounded-xl border border-gray-200 transition duration-150 cursor-pointer"
                >
                    Log Out
                </button>
            </form>
        </div>
    </div>

    </div>
</body>
</html>