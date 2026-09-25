<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SIEL CART</title>

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

        {{-- Card Title & Instructions --}}
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 leading-snug">
                Forgot password?
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Remember your password? 
                <a href="{{ route('login') }}" class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] hover:underline font-semibold">Sign in here</a>
            </p>
        </div>

        {{-- Success Session Alert --}}
        @if (session('status'))
            <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200/60 p-3 text-xs text-emerald-800 flex items-start gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-[0.6875rem] font-bold tracking-wider text-gray-500 uppercase mb-1.5">
                    EMAIL
                </label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="you@clsu.edu.ph"
                        required
                        autofocus
                        class="w-full text-xs sm:text-sm pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-secondary)] transition duration-150"
                    >
                </div>

                @error('email')
                    <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <svg class="w-3 h-3 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Submit Button --}}
            <button 
                type="submit" 
                class="w-full mt-2 py-2.5 px-4 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] active:brightness-90 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2 cursor-pointer"
            >
                <span>Send Reset Link</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

    </div>

    </div>

</body>

</html>
