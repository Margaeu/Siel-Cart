<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
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
                    Welcome back
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-semibold text-[#E0A70D] hover:underline">
                        Sign up
                    </a>
                </p>
            </div>

            <!-- Login Form Card -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg border-t-4 border-[#E0A70D]">
                @if (session('status'))
                    <div class="mb-4 bg-green-50 border border-[#1E6031] text-[#1E6031] px-4 py-3 rounded-lg text-sm">
                        {{ session('status') }}
                    </div>
                @endif

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
                               value="{{ old('email') }}"
                               required 
                               autofocus
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#1E6031] outline-none transition">
                        @error('email')
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
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#E0A70D] focus:border-[#1E6031] outline-none transition">
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   name="remember" 
                                   class="w-4 h-4 text-[#1E6031] border-gray-300 rounded focus:ring-[#E0A70D]">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>

                        <a href="{{ route('password.request') }}" 
                           class="text-sm font-medium text-[#1E6031] hover:text-[#E0A70D] transition">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-[#1E6031] text-white py-3 px-4 rounded-lg hover:bg-[#164724] active:bg-[#0f3018] focus:outline-none focus:ring-2 focus:ring-[#E0A70D] focus:ring-offset-2 transition font-semibold shadow-md">
                        Sign In
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