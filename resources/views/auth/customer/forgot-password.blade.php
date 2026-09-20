<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SIEL CART</title>

    {{-- Tailwind & Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#edf3ec] text-gray-800 font-sans antialiased flex items-center justify-center p-4">

    {{-- Main Auth Card --}}
    <div class="w-full max-w-[420px] bg-white rounded-3xl p-8 sm:p-10 shadow-[0_10px_35px_-5px_rgba(0,0,0,0.06)] border border-gray-100">

        {{-- Brand / Header --}}
        <div class="mb-8 flex items-center">
            <x-customer-auth-brand />
        </div>

        {{-- Card Title & Instructions --}}
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 leading-snug">
                Forgot password?
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Remember your password? 
                <a href="{{ route('login') }}" class="text-[#4d7318] hover:underline font-semibold">Sign in here</a>
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
                <label for="email" class="block text-[11px] font-bold tracking-wider text-gray-500 uppercase mb-1.5">
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
                        class="w-full text-xs sm:text-sm pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#a37b12] focus:ring-2 focus:ring-[#f6e6aa] transition duration-150"
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
                class="w-full mt-2 py-2.5 px-4 bg-[#557e1b] hover:bg-[#466a15] active:bg-[#385611] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2 cursor-pointer"
            >
                <span>Send Reset Link</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

        {{-- Footer Details --}}
        <div class="mt-8 flex items-center justify-center gap-1.5 text-[11px] text-gray-400">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>Secure sign-in</span>
            <span>·</span>
            <a href="#" class="hover:underline hover:text-gray-600">Privacy Policy</a>
        </div>

    </div>

</body>

</html>