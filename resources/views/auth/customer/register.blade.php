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
                        <input id="password" 
                               type="password" 
                               name="password" 
                               required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Confirmation -->
                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <input id="password_confirmation" 
                               type="password" 
                               name="password_confirmation" 
                               required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#557F13] outline-none transition">
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
</body>
</html>
