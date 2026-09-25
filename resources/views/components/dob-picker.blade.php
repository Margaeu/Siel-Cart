@props([
    'name' => 'date_of_birth',
    'value' => null,
    'label' => 'Date of Birth',
    'minAge' => 13,
    'required' => true,
])

@php
    // The browser's own <input type="date"> and its calendar popup can't be
    // styled to match the store. Instead the customer types MM/DD/YYYY into a
    // normal text box (slashes are inserted for them) or opens the custom
    // calendar below from the icon. Both write the hidden `{{ $name }}`
    // input (Y-m-d) the server validates.
    //
    // $minAge mirrors the server-side `before_or_equal:` rule wherever this
    // component is used (CreateNewCustomer at registration, Profile's
    // updateProfile on the storefront). Pass the same value the caller's
    // validation rule uses, or the two disagree.
    $dobMax = now()->subYears($minAge)->startOfDay();
    $dobValue = old($name, $value);
    $dobDisplay = $dobValue && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dobValue, $m)
        ? "{$m[2]}/{$m[3]}/{$m[1]}"
        : '';

    // IDs are namespaced by $name so more than one instance could in
    // principle render on the same page without colliding.
    $displayId = $name.'_display';
    $buttonId = $name.'_picker_button';
    $calendarId = $name.'_calendar';
    $gridId = $name.'_cal_grid';
    $monthId = $name.'_cal_month';
    $yearId = $name.'_cal_year';
    $prevId = $name.'_cal_prev';
    $nextId = $name.'_cal_next';
    $hintId = $name.'_hint';
    $tooYoungMessage = "You must be at least {$minAge} years old.";
@endphp

<div>
    <label for="{{ $displayId }}" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
        {{ $label }}
    </label>

    {{--
        The hidden input carries the attribute bag (e.g. wire:model from a
        Livewire caller) so the JS-managed value participates in whatever
        submission mechanism the caller uses. sync() below dispatches an
        `input` event after setting .value, since Livewire only listens for
        real events, not property assignment.
    --}}
    <input type="hidden"
           id="{{ $name }}"
           name="{{ $name }}"
           value="{{ $dobValue }}"
           {{ $attributes }}>

    <div class="relative">
        <input id="{{ $displayId }}"
               type="text"
               inputmode="numeric"
               autocomplete="bday"
               placeholder="MM/DD/YYYY"
               maxlength="10"
               value="{{ $dobDisplay }}"
               @if ($required) required @endif
               aria-describedby="{{ $hintId }}"
               data-max="{{ $dobMax->format('Y-m-d') }}"
               class="w-full rounded-lg border {{ $errors->has($name) ? 'border-red-400' : 'border-gray-300' }} py-2.5 pl-4 pr-12 tabular-nums placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition">

        <button type="button"
                id="{{ $buttonId }}"
                aria-label="Choose date from calendar"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="{{ $calendarId }}"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-gray-400 hover:text-[var(--color-primary)] focus:outline-none focus-visible:text-[var(--color-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] transition">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
        </button>

        {{--
            Calendar popover. The day grid is drawn by the script at the
            bottom of this component; month/year selects let a birth year be
            reached in one pick instead of paging back month by month.
        --}}
        @php
            $calSelectClass = 'appearance-none rounded-md border border-gray-200 bg-white bg-[length:0.875rem] bg-[right_0.35rem_center] bg-no-repeat py-1 pl-2 pr-5 text-[0.8125rem] font-semibold text-gray-900 hover:border-[var(--color-primary)] focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-secondary)] transition';
            $calChevron = "background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2.5' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E\")";
            $calNavClass = 'flex size-8 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-[var(--color-primary)]/10 hover:text-[var(--color-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] disabled:pointer-events-none disabled:opacity-30 transition';
        @endphp
        <div id="{{ $calendarId }}"
             role="dialog"
             aria-label="Choose {{ strtolower($label) }}"
             hidden
             class="absolute left-0 top-full z-30 mt-1.5 w-full max-w-[16.5rem] rounded-xl border border-gray-200 bg-white p-2.5 shadow-xl shadow-black/10">
            <div class="flex items-center justify-between gap-1">
                <button type="button" id="{{ $prevId }}" aria-label="Previous month" class="{{ $calNavClass }}">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="flex min-w-0 gap-1.5">
                    <label for="{{ $monthId }}" class="sr-only">Month</label>
                    <select id="{{ $monthId }}" class="{{ $calSelectClass }}" style="{{ $calChevron }}">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month - 1 }}">{{ \Carbon\Carbon::create(2000, $month, 1)->format('M') }}</option>
                        @endforeach
                    </select>

                    <label for="{{ $yearId }}" class="sr-only">Year</label>
                    <select id="{{ $yearId }}" class="{{ $calSelectClass }}" style="{{ $calChevron }}">
                        @foreach (range($dobMax->year, $dobMax->year - 100) as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" id="{{ $nextId }}" aria-label="Next month" class="{{ $calNavClass }}">
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

            <div id="{{ $gridId }}" class="mt-1 grid grid-cols-7 gap-0.5"></div>
        </div>
    </div>

    <p id="{{ $hintId }}" class="mt-1.5 text-xs text-gray-500">You must be at least {{ $minAge }} years old{{ $required ? ' to register' : '' }}.</p>

    @error($name)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<script>
    (function () {
        function init() {
            const dob = document.getElementById(@js($name));
            const display = document.getElementById(@js($displayId));
            const button = document.getElementById(@js($buttonId));
            const calendar = document.getElementById(@js($calendarId));
            const grid = document.getElementById(@js($gridId));
            const monthSelect = document.getElementById(@js($monthId));
            const yearSelect = document.getElementById(@js($yearId));
            const prev = document.getElementById(@js($prevId));
            const next = document.getElementById(@js($nextId));
            if (!dob || !display || !button || !calendar || !grid) return;

            // data-max mirrors the server-side before_or_equal: today minus
            // {{ $minAge }} years. Change both together.
            const max = display.dataset.max;
            const [maxY, maxM] = max.split('-').map(Number);
            const minY = Number(yearSelect.options[yearSelect.options.length - 1].value);
            const tooYoung = @js($tooYoungMessage);
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
                // Livewire (and anything else bound via wire:model) only
                // reacts to real events, not the .value assignment above.
                dob.dispatchEvent(new Event('input', { bubbles: true }));

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
        }

        // Livewire swaps the DOM on every request (e.g. after a failed
        // updateProfile validation), which re-runs this <script> without a
        // fresh DOMContentLoaded event, so the listener setup above must run
        // again each time this component's markup lands in the DOM.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
