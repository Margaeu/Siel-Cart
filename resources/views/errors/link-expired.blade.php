<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Link Expired' }} - {{ config('app.name') }}</title>

    {{-- Tailwind & Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @include('partials.theme-styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-50 text-gray-800 font-sans antialiased flex flex-col">

    <x-customer-auth-header />

    <div class="flex flex-1 items-center justify-center p-4">

    {{-- Main Card --}}
    <div class="w-full max-w-[26.25rem] bg-white rounded-3xl p-8 sm:p-10 shadow-[0_10px_35px_-5px_rgba(0,0,0,0.06)] border border-gray-100">

        {{-- Status Icon --}}
        <div class="flex justify-center mb-5">
            <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
        </div>

        {{-- Heading & Message --}}
        <div class="text-center mb-6">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 leading-snug">
                {{ $heading ?? 'This link has expired' }}
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-2 leading-relaxed">
                {{ $message ?? 'For your security, this link is no longer valid. Please request a new one.' }}
            </p>
        </div>

        {{-- Actions --}}
        <div class="space-y-2.5">
            @if (! empty($primaryUrl))
                <a href="{{ $primaryUrl }}"
                   class="w-full py-2.5 px-4 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] active:brightness-90 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
                >
                    <span>{{ $primaryLabel ?? 'Continue' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            @endif

            @if (! empty($secondaryUrl))
                <a href="{{ $secondaryUrl }}"
                   class="w-full py-2.5 px-4 bg-transparent hover:bg-gray-50 text-gray-600 text-xs sm:text-sm font-semibold rounded-xl border border-gray-200 transition duration-150 flex items-center justify-center"
                >
                    {{ $secondaryLabel ?? 'Back to Sign In' }}
                </a>
            @endif
        </div>
    </div>

    </div>

</body>

</html>
