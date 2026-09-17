<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f4f7ef]">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- University logo and store name -->
            <div class="text-center mb-8">
                {{-- University logo header (temporarily disabled) --}}
                {{-- <x-customer-auth-brand /> --}}

                <a href="{{ route('home') }}" class="text-3xl font-bold text-[#557F13]">
                    {{ config('app.name') }}
                </a>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Welcome back
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-semibold text-[#2563EB] hover:text-[#1D4ED8] hover:underline transition">
                        Sign up
                    </a>
                </p>
            </div>

            <!-- Login Form Card -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg border-t-4 border-[#E0A70D]">
                @if (session('status'))
                    <div class="mb-4 bg-green-50 border border-[#557F13] text-[#557F13] px-4 py-3 rounded-lg text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @php
                    // A failed sign-in clears both fields (the email is not
                    // repopulated) but highlights both so the error stays clear.
                    $loginFailed = $errors->has('email');
                    $inputBase = 'w-full px-4 py-2 border rounded-lg focus:outline-none transition';
                    $inputValid = 'border-gray-300 focus:border-[#557F13]';
                    $inputInvalid = 'border-red-500 focus:border-red-600';
                @endphp

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               required
                               autofocus
                               @if ($loginFailed) aria-invalid="true" @endif
                               @class([$inputBase, $inputInvalid => $loginFailed, $inputValid => ! $loginFailed])>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   @if ($loginFailed || $errors->has('password')) aria-invalid="true" @endif
                                   @class([$inputBase, 'pr-11', $inputInvalid => $loginFailed || $errors->has('password'), $inputValid => ! ($loginFailed || $errors->has('password'))])>
                            <button type="button"
                                    data-password-toggle="password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 rounded-r-lg hover:text-[#557F13] focus:outline-none focus:text-[#557F13] transition">
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
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="remember" 
                                   class="w-4 h-4 text-[#557F13] border-gray-300 rounded focus:ring-[#E0A70D]">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>

                        <a href="{{ route('password.request') }}" 
                           class="text-sm font-medium text-[#557F13] hover:text-[#E0A70D] transition">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-[#557F13] text-white py-3 px-4 rounded-lg hover:bg-[#3E5D0E] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2 transition font-semibold shadow-md">
                        Sign In
                    </button>
                </form>
            </div>

            <!-- Back to Home -->
            <p class="mt-6 text-center text-sm text-gray-600">
                <a href="{{ route('home') }}" class="font-medium text-[#557F13] hover:text-[#E0A70D] transition">
                    ← Back to Home
                </a>
            </p>
        </div>
    </div>

    <x-password-toggle-script />
</body>
</html>
