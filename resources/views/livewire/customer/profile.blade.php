{{--
    The tab follows the URL hash so the sidebar's "Password" link
    (#password) opens the Security tab, including when it is clicked while
    already on this page, where only the hash changes and nothing reloads.
--}}
<div class="bg-gray-50 min-h-screen py-10"
     x-data="{ tab: location.hash === '#password' ? 'security' : 'profile' }"
     x-on:hashchange.window="tab = location.hash === '#password' ? 'security' : 'profile'">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- Left Sidebar --}}
            <aside class="lg:col-span-3">
                @include('partials.customer-account-sidebar', ['active' => 'profile'])
            </aside>

            {{-- Main Content Column --}}
            <main class="lg:col-span-9 space-y-6">

        <!-- Header -->
        <div>
            <span class="text-xs font-semibold tracking-wider text-gray-400 uppercase">My Account</span>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Profile</h1>
        </div>

        <!-- Profile Cover -->
<div class="overflow-hidden rounded-2xl border border-[#eeebe2] bg-white shadow-sm">
    {{-- Header Banner --}}
    <div class="h-28 bg-gradient-to-r from-[#14532d] via-[#166534] to-[#ca8a04] sm:h-36"></div>

    {{-- Body Container --}}
    <div class="relative px-6 pb-6 pt-4 sm:px-8 sm:pb-8">
        
        {{-- Avatar positioned exactly over the border seam --}}
        <div class="absolute -top-12 sm:-top-14 left-6 sm:left-8 flex size-24 sm:size-28 items-center justify-center rounded-full bg-[#14532d] ring-4 ring-white shadow-md">
            {{-- Optical centering for capital letters --}}
            <span class="translate-y-[1px] text-3xl sm:text-4xl font-black tracking-tight text-[#facc15] leading-none select-none text-center">
                {{ auth('customer')->user()->initials() }}
            </span>
        </div>

        {{-- Text & Controls with generous spacing from the banner line --}}
        <div class="pt-14 sm:pt-2 sm:pl-32 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 leading-tight">
                    {{ auth('customer')->user()->name }}
                </h1>
                <p class="mt-1 text-sm font-medium text-gray-500">
                    {{ auth('customer')->user()->email }}
                </p>
            </div>

            <div class="inline-flex items-center gap-2 self-start sm:self-center rounded-full border border-[#eeebe2] bg-[#faf9f5] px-3.5 py-1.5 text-xs font-medium text-gray-600">
                <svg class="size-3.5 text-[#14532d]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Member since {{ auth('customer')->user()->created_at->format('M d, Y') }}
            </div>
        </div>

    </div>
</div>

        <!-- Tabbed Settings -->
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="flex gap-1 border-b border-gray-100 px-4">
                <button type="button"
                        @click="tab = 'profile'; history.replaceState(null, '', location.pathname)"
                        :class="tab === 'profile' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="flex items-center gap-2 border-b-2 px-4 py-4 text-sm font-semibold transition">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Profile Information
                </button>
                <button type="button"
                        @click="tab = 'security'; history.replaceState(null, '', '#password')"
                        :class="tab === 'security' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="flex items-center gap-2 border-b-2 px-4 py-4 text-sm font-semibold transition">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Security
                </button>
            </div>

            <div class="p-6 sm:p-8">

                <!-- Profile Information -->
                <div x-show="tab === 'profile'">
                    @if (session()->has('profile_success'))
                        <div class="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ session('profile_success') }}
                        </div>
                    @endif

                    <form wire:submit="updateProfile" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">First Name *</label>
                                <input type="text"
                                       wire:model="first_name"
                                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @error('first_name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Last Name *</label>
                                <input type="text"
                                       wire:model="last_name"
                                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @error('last_name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email"
                                   wire:model="email"
                                   class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Phone</label>
                            <input type="tel"
                                   wire:model="phone"
                                   class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            @error('phone') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                    class="transform rounded-xl bg-[var(--color-primary)] px-6 py-2.5 font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[var(--color-primary-hover)] hover:shadow-md">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security / Change Password -->
                <div x-show="tab === 'security'" x-cloak>
                    @if (session()->has('password_success'))
                        <div class="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ session('password_success') }}
                        </div>
                    @endif
                    @if (session()->has('password_error'))
                        <div class="mb-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            {{ session('password_error') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
                        <form wire:submit="updatePassword" class="space-y-4 lg:col-span-3">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Current Password</label>
                                <input type="password"
                                       wire:model="current_password"
                                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @error('current_password') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password"
                                       wire:model="new_password"
                                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                                @error('new_password') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password"
                                       wire:model="new_password_confirmation"
                                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20">
                            </div>

                            <div class="pt-2">
                                <button type="submit"
                                        class="transform rounded-xl bg-[var(--color-primary)] px-6 py-2.5 font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[var(--color-primary-hover)] hover:shadow-md">
                                    Change Password
                                </button>
                            </div>
                        </form>

                        <!-- Password tips: fills the space beside the form and doubles as a quick security nudge -->
                        <div class="lg:col-span-2">
                            <div class="rounded-xl bg-gray-50 p-5">
                                <div class="mb-3 flex items-center gap-2">
                                    <svg class="size-4.5 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="text-sm font-semibold text-gray-900">Keep your account secure</h3>
                                </div>
                                <ul class="space-y-2.5 text-sm text-gray-600">
                                    <li class="flex gap-2">
                                        <span class="mt-1.5 size-1 shrink-0 rounded-full bg-[var(--color-primary)]"></span>
                                        Use at least 8 characters with a mix of letters and numbers.
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-1.5 size-1 shrink-0 rounded-full bg-[var(--color-primary)]"></span>
                                        Avoid reusing a password from another site.
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-1.5 size-1 shrink-0 rounded-full bg-[var(--color-primary)]"></span>
                                        Never share your password, even with store staff.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
            </main>
        </div>
    </div>
</div>
