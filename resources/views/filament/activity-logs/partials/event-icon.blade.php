{{--
    The coloured circle on the timeline rail.
    Expects: $presenter (App\Support\ActivityLogPresenter), optional $size ('md'|'lg').

    Classes are written out in full (never assembled from pieces) so
    Tailwind's scanner can find them when the admin theme is built.
--}}
@php
    $size ??= 'md';

    $toneClasses = match ($presenter->eventTone()) {
        'green' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/20',
        'red' => 'bg-red-50 text-red-600 ring-red-600/20',
        'blue' => 'bg-sky-50 text-sky-600 ring-sky-600/20',
        'orange' => 'bg-orange-50 text-orange-600 ring-orange-600/20',
        default => 'bg-gray-50 text-gray-500 ring-gray-500/20',
    };
@endphp

<span
    @class([
        'relative z-10 inline-flex shrink-0 items-center justify-center rounded-full ring-1 ring-inset',
        'size-10' => $size === 'lg',
        'size-8' => $size !== 'lg',
        $toneClasses,
    ])
    title="{{ $presenter->eventLabel() }}"
>
    <x-filament::icon
        :icon="$presenter->eventIcon()"
        @class(['size-5' => $size === 'lg', 'size-4' => $size !== 'lg'])
    />
    <span class="sr-only">{{ $presenter->eventLabel() }}</span>
</span>
