<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Password - {{ config('app.name') }}</title>
    @include('partials.theme-styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-gray-50">
    {{--
        Fortify's GET /user/confirm-password (password.confirm). Nothing in the
        app sends customers here today - no route uses the password.confirm
        middleware - but Fortify registers the route regardless, so it needs a
        page that matches the rest of the customer auth screens. This replaced
        the Flux starter-kit view (livewire/auth/confirm-password) when
        livewire/flux was removed.
    --}}
    <x-customer-auth-header />

    <div class="flex flex-1 items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-black/5 bg-white p-6 shadow-xl shadow-black/5 sm:p-8">
                <!-- Heading -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Confirm your password</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        This is a secure area of your account. Please confirm your password before continuing.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/5 px-4 py-3 text-sm text-[var(--color-primary-hover)]">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <!-- Password -->
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Password
                        </label>
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
                                   autofocus
                                   autocomplete="current-password"
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
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] py-3 px-4 font-semibold text-white shadow-md transition hover:bg-[var(--color-primary-hover)] active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] focus:ring-offset-2">
                        Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>

    <x-password-toggle-script />
</body>
</html>
