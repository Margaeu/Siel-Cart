<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-green-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="text-3xl font-bold text-[#1E6031]">
                    {{ config('app.name') }}
                </a>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Verify your email
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    One last step before you can shop.
                </p>
            </div>

            <!-- Verification Card -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg border-t-4 border-[#E0A70D]">
                @if (session('verify_reason'))
                    <div class="mb-6 bg-amber-50 border border-[#E0A70D] text-amber-900 px-4 py-3 rounded-lg text-sm">
                        {{ session('verify_reason') }}
                    </div>
                @endif

                @if (session('status') === 'verification-link-sent')
                    <div class="mb-6 bg-green-50 border border-[#1E6031] text-[#1E6031] px-4 py-3 rounded-lg text-sm">
                        A fresh verification link is on its way. Check your inbox again in a moment.
                    </div>
                @endif

                <!-- Envelope Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center">
                        <svg class="w-8 h-8 text-[#1E6031]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <p class="text-sm text-gray-600 text-center">
                    We sent a verification link to
                </p>
                <p class="text-base font-semibold text-[#1E6031] text-center break-all mt-1 mb-4">
                    {{ auth('customer')->user()->email }}
                </p>
                <p class="text-sm text-gray-600 text-center mb-6">
                    Click the link in that email to activate your account. If it has not arrived,
                    check your spam folder or send yourself a new one.
                </p>

                <!-- Resend -->
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit"
                            class="w-full bg-[#1E6031] text-white py-3 px-4 rounded-lg hover:bg-[#164724] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2 transition font-semibold shadow-md">
                        Resend Verification Email
                    </button>
                </form>

                <!-- Log Out -->
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit"
                            class="w-full bg-white text-gray-700 border border-gray-300 py-3 px-4 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2 transition font-medium">
                        Log Out
                    </button>
                </form>
            </div>

            <!-- Back to Home -->
            <p class="mt-6 text-center text-sm text-gray-600">
                <a href="{{ route('home') }}" class="font-medium text-[#1E6031] hover:text-[#E0A70D] transition">
                    ← Back to Home
                </a>
            </p>
        </div>
    </div>
</body>
</html>
