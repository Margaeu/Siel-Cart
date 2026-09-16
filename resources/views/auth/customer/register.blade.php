<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
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
                    Create your account
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-semibold text-[#2563EB] hover:text-[#1D4ED8] hover:underline transition">
                        Sign in
                    </a>
                </p>
            </div>

            <!-- Registration Form Card -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg border-t-4 border-[#E0A70D]">
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- First Name and Last Name Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                First Name
                            </label>
                            <input id="first_name" 
                                   type="text" 
                                   name="first_name" 
                                   value="{{ old('first_name') }}"
                                   required 
                                   autofocus
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                            @error('first_name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Last Name
                            </label>
                            <input id="last_name" 
                                   type="text" 
                                   name="last_name" 
                                   value="{{ old('last_name') }}"
                                   required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                            @error('last_name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Date of Birth -->
                    <div class="mb-4">
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
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
                               max="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">

                        @error('date_of_birth')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input id="email" 
                               type="email" 
                               name="email" 
                               value="{{ old('email') }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div class="mb-4">
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number (Optional)
                        </label>
                        <input id="phone" 
                               type="tel" 
                               name="phone" 
                               value="{{ old('phone') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                        @error('phone')
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
                                   class="w-full px-4 py-2 pr-11 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
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

                    <!-- Password Confirmation -->
                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   class="w-full px-4 py-2 pr-11 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                            <button type="button"
                                    data-password-toggle="password_confirmation"
                                    aria-controls="password_confirmation"
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
                    </div>

                    <!-- Terms -->
                    <div class="mb-6">
                        <label class="flex items-start cursor-pointer">
                            <input type="checkbox" 
                                   required
                                   class="w-4 h-4 text-[#557F13] border-gray-300 rounded focus:ring-[#E0A70D] mt-1">
                            <span class="ml-2 text-sm text-gray-600">
                                I agree to the 
                                <!--<a href="#" class="text-[#557F13] font-medium hover:text-[#E0A70D] transition">Terms and Conditions</a>-->
                                <a href="#" class="text-[#557F13] font-medium hover:text-[#E0A70D] transition">Privacy Policy</a>
                            </span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-[#557F13] text-white py-3 px-4 rounded-lg hover:bg-[#3E5D0E] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2 transition font-semibold shadow-md">
                        Create Account
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
