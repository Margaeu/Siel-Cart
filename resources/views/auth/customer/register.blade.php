<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Create your account</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-semibold text-[#557F13] hover:text-[#3E5D0E] hover:underline transition">
                            Sign in
                        </a>
                    </p>
                </div>

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
                    @csrf

                    <!-- First Name and Last Name -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                First Name
                            </label>
                            <input id="first_name"
                                   type="text"
                                   name="first_name"
                                   value="{{ old('first_name') }}"
                                   required
                                   autofocus
                                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                            @error('first_name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Last Name
                            </label>
                            <input id="last_name"
                                   type="text"
                                   name="last_name"
                                   value="{{ old('last_name') }}"
                                   required
                                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                            @error('last_name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Date of Birth
                        </label>

                        <!--
                            The date input provides a calendar picker in supported browsers.
                            The old() value keeps the entered date after a validation error.
                        -->
                        <input id="date_of_birth"
                               type="date"
                               name="date_of_birth"
                               value="{{ old('date_of_birth') }}"
                               required
                               max="{{ date('Y-m-d', strtotime('-13 years')) }}"
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">

                        @error('date_of_birth')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

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
                                   placeholder="you@clsu.edu.ph"
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-4 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Phone Number <span class="font-normal normal-case text-gray-400">(optional)</span>
                        </label>
                        <input id="phone"
                               type="tel"
                               name="phone"
                               value="{{ old('phone') }}"
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

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

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </span>
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-11 focus:border-[#557F13] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] transition">
                            <button type="button"
                                    data-password-toggle="password_confirmation"
                                    aria-controls="password_confirmation"
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
                    </div>

                    <!-- Terms -->
                    <label class="flex items-start gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               required
                               class="mt-0.5 size-4 rounded border-gray-300 text-[#557F13] focus:ring-[#E0A70D]">
                        <span class="text-sm text-gray-600">
                            I agree to the
                            <a href="{{ route('privacy-policy') }}" class="font-medium text-[#557F13] hover:text-[#E0A70D] transition">Privacy Policy</a>
                        </span>
                    </label>

                    <!-- Submit -->
                    <button type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-[#557F13] py-3 px-4 font-semibold text-white shadow-md transition hover:bg-[#3E5D0E] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2">
                        Create Account
                        <svg class="size-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>
                </p>
            </div>
        </div>
    </div>

    <x-password-toggle-script />
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dob = document.getElementById('date_of_birth');
            if (!dob) return;
            const message = 'You must be 13 years old to create an account.';

            function checkDob() {
                dob.setCustomValidity('');
                if (!dob.value) return;
                if (dob.max && new Date(dob.value) > new Date(dob.max)) {
                    dob.setCustomValidity(message);
                }
            }

            dob.addEventListener('input', checkDob);
            dob.addEventListener('invalid', function (e) {
                if (dob.validity.rangeOverflow) {
                    dob.setCustomValidity(message);
                }
            });
        });
    </script>
</body>
</html>