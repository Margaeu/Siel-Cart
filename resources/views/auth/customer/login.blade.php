<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-[#f4f7ef] via-[#f4f7ef] to-[#e7efdc] min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-xl shadow-black/5 sm:p-8">
                <!-- Brand -->
                <x-customer-auth-brand />

                <!-- Heading -->
                <div class="mt-7">
                    <h1 class="text-2xl font-bold text-gray-900">Welcome back</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="font-semibold text-[#557F13] hover:text-[#3E5D0E] hover:underline transition">
                            Register here
                        </a>
                    </p>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-[#557F13]/30 bg-[#557F13]/5 px-4 py-3 text-sm text-[#3E5D0E]">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <!-- Email -->
                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Email
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.007 1.872l-7.5 5a2.25 2.25 0 0 1-2.486 0l-7.5-5A2.25 2.25 0 0 1 2.25 6.993V6.75" />
                                </svg>
                            </span>
                            <input id="email"
                                   type="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   autofocus
                                   placeholder="you@clsu.edu.ph"
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-4 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Password
                            </label>
                            <a href="{{ route('password.request') }}" class="text-xs font-medium text-[#557F13] hover:text-[#E0A70D] transition">
                                Forgot password?
                            </a>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </span>
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-11 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                            <button type="button"
                                    data-password-toggle="password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 rounded-r-lg hover:text-[#557F13] focus:outline-none focus:text-[#557F13] transition">
                                <svg data-icon-show class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg data-icon-hide class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               name="remember"
                               class="size-4 rounded border-gray-300 text-[#557F13] focus:ring-[#E0A70D]">
                        <span class="text-sm text-gray-600">Remember me for 30 days</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-[#557F13] py-3 px-4 font-semibold text-white shadow-md transition hover:bg-[#3E5D0E] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2">
                        Sign In
                        <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>

                <!-- Trust footer -->
                <p class="mt-6 flex flex-wrap items-center justify-center gap-x-1.5 gap-y-1 text-xs text-gray-500">
                    <svg class="size-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286Z" />
                    </svg>
                    <span>Secure sign-in</span>
                    <span aria-hidden="true">&middot;</span>
                    <a href="{{ route('privacy-policy') }}" class="underline hover:text-[#557F13] transition">Privacy Policy</a>
                </p>
            </div>
        </div>
    </div>

    <x-password-toggle-script />
</body>
</html>