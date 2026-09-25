<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Create your account</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] hover:underline transition">
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
                                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
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
                                   class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
                            @error('last_name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Date of Birth -->
                    @php
                        // The browser's own <input type="date"> and its calendar popup can't
                        // be styled to match the store. Instead the customer types MM/DD/YYYY
                        // into a normal text box (slashes are inserted for them) or opens the
                        // custom calendar below from the icon. Both write the hidden
                        // `date_of_birth` (Y-m-d) the server validates, so CreateNewCustomer's
                        // rule is unchanged.
                        $dobMax = now()->subYears(13)->startOfDay();
                        $dobOld = old('date_of_birth');
                        $dobDisplay = $dobOld && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dobOld, $m)
                            ? "{$m[2]}/{$m[3]}/{$m[1]}"
                            : '';
                    @endphp
                    <div>
                        <label for="dob_display" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Date of Birth
                        </label>

                        <input type="hidden"
                               id="date_of_birth"
                               name="date_of_birth"
                               value="{{ $dobOld }}">

                        <div class="relative">
                            <input id="dob_display"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="bday"
                                   placeholder="MM/DD/YYYY"
                                   maxlength="10"
                                   value="{{ $dobDisplay }}"
                                   required
                                   aria-describedby="dob_hint"
                                   data-max="{{ $dobMax->format('Y-m-d') }}"
                                   class="w-full rounded-lg border {{ $errors->has('date_of_birth') ? 'border-red-400' : 'border-gray-300' }} py-2.5 pl-4 pr-12 tabular-nums placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">

                            <button type="button"
                                    id="dob_picker_button"
                                    aria-label="Choose date from calendar"
                                    aria-haspopup="dialog"
                                    aria-expanded="false"
                                    aria-controls="dob_calendar"
                                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-gray-400 hover:text-[var(--color-primary)] focus:outline-none focus-visible:text-[var(--color-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] transition">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </button>

                            {{--
                                Calendar popover. The day grid is drawn by the script at the
                                bottom of the page; month/year selects let a birth year be
                                reached in one pick instead of paging back month by month.
                            --}}
                            @php
                                $calSelectClass = 'appearance-none rounded-md border border-gray-200 bg-white bg-[length:0.875rem] bg-[right_0.35rem_center] bg-no-repeat py-1 pl-2 pr-5 text-[0.8125rem] font-semibold text-gray-900 hover:border-[var(--color-primary)] focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition';
                                $calChevron = "background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2.5' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E\")";
                                $calNavClass = 'flex size-8 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-[var(--color-primary)]/10 hover:text-[var(--color-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] disabled:pointer-events-none disabled:opacity-30 transition';
                            @endphp
                            <div id="dob_calendar"
                                 role="dialog"
                                 aria-label="Choose date of birth"
                                 hidden
                                 class="absolute left-0 top-full z-30 mt-1.5 w-full max-w-[16.5rem] rounded-xl border border-gray-200 bg-white p-2.5 shadow-xl shadow-black/10">
                                <div class="flex items-center justify-between gap-1">
                                    <button type="button" id="dob_cal_prev" aria-label="Previous month" class="{{ $calNavClass }}">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                        </svg>
                                    </button>

                                    <div class="flex min-w-0 gap-1.5">
                                        <label for="dob_cal_month" class="sr-only">Month</label>
                                        <select id="dob_cal_month" class="{{ $calSelectClass }}" style="{{ $calChevron }}">
                                            @foreach (range(1, 12) as $month)
                                                <option value="{{ $month - 1 }}">{{ \Carbon\Carbon::create(2000, $month, 1)->format('M') }}</option>
                                            @endforeach
                                        </select>

                                        <label for="dob_cal_year" class="sr-only">Year</label>
                                        <select id="dob_cal_year" class="{{ $calSelectClass }}" style="{{ $calChevron }}">
                                            @foreach (range($dobMax->year, $dobMax->year - 100) as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button type="button" id="dob_cal_next" aria-label="Next month" class="{{ $calNavClass }}">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-2 grid grid-cols-7 text-center text-[0.625rem] font-semibold uppercase tracking-wide text-gray-400" aria-hidden="true">
                                    @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $weekday)
                                        <span class="py-0.5">{{ $weekday }}</span>
                                    @endforeach
                                </div>

                                <div id="dob_cal_grid" class="mt-1 grid grid-cols-7 gap-0.5"></div>
                            </div>
                        </div>

                        <p id="dob_hint" class="mt-1.5 text-xs text-gray-500">You must be at least 13 years old to register.</p>

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
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-4 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
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
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
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
                                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-11 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">
                            <button type="button"
                                    data-password-toggle="password_confirmation"
                                    aria-controls="password_confirmation"
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
                    </div>

                    <!-- Terms -->
                    <label class="flex items-start gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               required
                               class="mt-0.5 size-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-secondary)]">
                        <span class="text-sm text-gray-600">
                            I agree to the
                            <a href="{{ route('privacy-policy') }}" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-secondary)] transition">Privacy Policy</a>
                            and
                             <a href="{{ route('terms-and-conditions') }}" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-secondary)] transition">Terms and Conditions</a>
                        </span>  
                    </label>

                    <!-- Submit -->
                    <button type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] py-3 px-4 font-semibold text-white shadow-md transition hover:bg-[var(--color-primary-hover)] active:brightness-90 focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] focus:ring-offset-2">
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
            const display = document.getElementById('dob_display');
            const button = document.getElementById('dob_picker_button');
            const calendar = document.getElementById('dob_calendar');
            const grid = document.getElementById('dob_cal_grid');
            const monthSelect = document.getElementById('dob_cal_month');
            const yearSelect = document.getElementById('dob_cal_year');
            const prev = document.getElementById('dob_cal_prev');
            const next = document.getElementById('dob_cal_next');
            if (!dob || !display || !button || !calendar || !grid) return;

            // data-max mirrors CreateNewCustomer's before_or_equal: today minus
            // 13 years. Change both together.
            const max = display.dataset.max;
            const [maxY, maxM] = max.split('-').map(Number);
            const minY = Number(yearSelect.options[yearSelect.options.length - 1].value);
            const tooYoung = 'You must be 13 years old to create an account.';
            const badDate = 'Enter a valid date as MM/DD/YYYY.';

            // Keep only digits and re-insert the slashes, so "01152000" and
            // "1/15/2000" pasted or typed both end up as "01/15/2000". A slash the
            // customer typed closes that part, so a one-digit month/day is padded.
            function format(raw) {
                const parts = raw.split('/');
                const digits = parts.map(function (part, i) {
                    const d = part.replace(/\D/g, '');
                    return i < parts.length - 1 && i < 2 && d.length === 1 ? '0' + d : d;
                }).join('').slice(0, 8);
                let out = digits.slice(0, 2);
                if (digits.length > 2) out += '/' + digits.slice(2, 4);
                if (digits.length > 4) out += '/' + digits.slice(4);
                return out;
            }

            // MM/DD/YYYY → Y-m-d, or null if it isn't a real calendar date
            // (rejects 02/30, 13/01, …).
            function toIso(text) {
                const m = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                if (!m) return null;
                const [, mm, dd, yyyy] = m;
                const d = new Date(+yyyy, +mm - 1, +dd);
                if (d.getFullYear() !== +yyyy || d.getMonth() !== +mm - 1 || d.getDate() !== +dd) {
                    return null;
                }
                return yyyy + '-' + mm + '-' + dd;
            }

            function sync() {
                const iso = toIso(display.value);
                dob.value = iso || '';

                display.setCustomValidity('');
                if (!display.value) return; // `required` handles the empty case
                if (!iso) {
                    display.setCustomValidity(badDate);
                } else if (iso > max) {
                    display.setCustomValidity(tooYoung);
                }
            }

            display.addEventListener('input', function (e) {
                // Let backspace remove a slash without it being re-added at once.
                const deleting = (e.inputType || '').startsWith('delete');
                if (!deleting) display.value = format(display.value);
                sync();
            });
            display.addEventListener('blur', function () {
                display.value = format(display.value);
                sync();
            });

            // ---- Calendar popover -------------------------------------------
            const pad = (n) => String(n).padStart(2, '0');
            const isoOf = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
            const daysIn = (y, m) => new Date(y, m + 1, 0).getDate();
            const dayClass = 'flex h-8 w-full items-center justify-center rounded-md text-[0.8125rem] tabular-nums transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]';
            const dayStates = {
                normal: 'text-gray-700 hover:bg-[var(--color-primary)]/10 hover:text-[var(--color-primary)]',
                selected: 'bg-[var(--color-primary)] font-semibold text-white shadow-sm hover:bg-[var(--color-primary-hover)]',
                disabled: 'cursor-not-allowed text-gray-300',
            };

            // Month on screen, and the day the arrow keys move around.
            let viewY = maxY;
            let viewM = maxM - 1;
            let focusDay = 1;

            function clampView() {
                if (viewY > maxY || (viewY === maxY && viewM > maxM - 1)) {
                    viewY = maxY;
                    viewM = maxM - 1;
                }
                if (viewY < minY) {
                    viewY = minY;
                    viewM = 0;
                }
            }

            function render() {
                clampView();
                monthSelect.value = String(viewM);
                yearSelect.value = String(viewY);
                // Months after the minimum-age month can't be chosen in that year.
                Array.from(monthSelect.options).forEach(function (opt) {
                    opt.disabled = viewY === maxY && Number(opt.value) > maxM - 1;
                });
                prev.disabled = viewY === minY && viewM === 0;
                next.disabled = viewY === maxY && viewM === maxM - 1;

                const total = daysIn(viewY, viewM);
                focusDay = Math.min(Math.max(focusDay, 1), total);
                const selected = dob.value;
                grid.replaceChildren();

                // Blank cells before the 1st so days line up under their weekday.
                for (let i = 0; i < new Date(viewY, viewM, 1).getDay(); i++) {
                    grid.appendChild(document.createElement('span'));
                }

                for (let d = 1; d <= total; d++) {
                    const iso = isoOf(viewY, viewM, d);
                    const disabled = iso > max;
                    const state = disabled ? 'disabled' : (iso === selected ? 'selected' : 'normal');
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.textContent = d;
                    cell.dataset.day = d;
                    cell.className = dayClass + ' ' + dayStates[state];
                    cell.disabled = disabled;
                    cell.tabIndex = d === focusDay ? 0 : -1;
                    cell.setAttribute('aria-label', new Date(viewY, viewM, d).toLocaleDateString('en-US', {
                        month: 'long', day: 'numeric', year: 'numeric',
                    }));
                    if (state === 'selected') cell.setAttribute('aria-pressed', 'true');
                    grid.appendChild(cell);
                }
            }

            function openCalendar() {
                // Open on the chosen date, else on the newest allowed month.
                if (dob.value) {
                    const [y, m, d] = dob.value.split('-').map(Number);
                    viewY = y; viewM = m - 1; focusDay = d;
                } else {
                    viewY = maxY; viewM = maxM - 1; focusDay = 1;
                }
                render();
                calendar.hidden = false;
                button.setAttribute('aria-expanded', 'true');
                // Start keyboard users on the year, the part most likely to change.
                yearSelect.focus();
            }

            function closeCalendar(returnFocus) {
                if (calendar.hidden) return;
                calendar.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                if (returnFocus) button.focus();
            }

            function shiftMonth(delta) {
                viewM += delta;
                if (viewM < 0) { viewM = 11; viewY--; }
                if (viewM > 11) { viewM = 0; viewY++; }
                render();
            }

            button.addEventListener('click', function () {
                calendar.hidden ? openCalendar() : closeCalendar(false);
            });
            prev.addEventListener('click', function () { shiftMonth(-1); });
            next.addEventListener('click', function () { shiftMonth(1); });
            monthSelect.addEventListener('change', function () { viewM = Number(monthSelect.value); render(); });
            yearSelect.addEventListener('change', function () { viewY = Number(yearSelect.value); render(); });

            grid.addEventListener('click', function (e) {
                const cell = e.target.closest('button[data-day]');
                if (!cell || cell.disabled) return;
                display.value = pad(viewM + 1) + '/' + pad(cell.dataset.day) + '/' + viewY;
                sync();
                closeCalendar(false);
                display.focus();
            });

            // Arrow keys walk the days, crossing into the next/previous month.
            grid.addEventListener('keydown', function (e) {
                const cell = e.target.closest('button[data-day]');
                if (!cell) return;
                const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
                if (!step) return;
                e.preventDefault();
                const target = new Date(viewY, viewM, Number(cell.dataset.day) + step);
                if (isoOf(target.getFullYear(), target.getMonth(), target.getDate()) > max) return;
                if (target.getFullYear() < minY) return;
                viewY = target.getFullYear();
                viewM = target.getMonth();
                focusDay = target.getDate();
                render();
                grid.querySelector('button[data-day="' + focusDay + '"]').focus();
            });

            calendar.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeCalendar(true);
                }
            });

            // Close when clicking or tabbing anywhere outside the field + popover.
            document.addEventListener('mousedown', function (e) {
                if (!calendar.contains(e.target) && !button.contains(e.target)) closeCalendar(false);
            });
            document.addEventListener('focusin', function (e) {
                if (!calendar.contains(e.target) && e.target !== button) closeCalendar(false);
            });

            sync();
        });
    </script>
</body>
</html>