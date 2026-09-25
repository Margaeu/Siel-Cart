<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    @include('partials.theme-styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-gradient-to-br from-[#f4f7ef] via-[#f4f7ef] to-[#e7efdc]">
    <x-customer-auth-header />

    <div class="flex flex-1 items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-xl shadow-black/5 sm:p-8">
                <!-- Heading -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Welcome back</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] hover:underline transition">
                            Register here
                        </a>
                    </p>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/5 px-4 py-3 text-sm text-[var(--color-primary-hover)]">
                        {{ session('status') }}
                    </div>
                @endif

                @error('email')
                    <div class="mt-6 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

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
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-4 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Password
                            </label>
                            <a href="{{ route('password.request') }}" class="text-xs font-medium text-[var(--color-primary)] hover:text-[var(--color-secondary)] transition">
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
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-11 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
                            <button type="button"
                                    data-password-toggle="password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 rounded-r-lg hover:text-[var(--color-primary)] focus:outline-none focus:text-[var(--color-primary)] transition">
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

                    <!-- Submit -->
                    <button type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] py-3 px-4 font-semibold text-white shadow-md transition hover:bg-[var(--color-primary-hover)] active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] focus:ring-offset-2">
                        Sign In
                        <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <x-password-toggle-script />
</body>
</html>