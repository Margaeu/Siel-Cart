{{--
    One formatted value from ActivityLogPresenter::changes().
    Expects: $cell (array{display, full, truncated}), $sensitive (bool), optional $valueClass.

    Everything is printed with {{ }} so logged user input is always escaped.
    A shortened value keeps the full text one click away in a native
    <details>, which is keyboard- and screen-reader-accessible without JS.
--}}
@php
    $valueClass ??= '';
@endphp

@if ($sensitive)
    <span class="inline-flex items-center gap-1 italic text-gray-400">
        <x-filament::icon icon="heroicon-m-eye-slash" class="size-3.5" />
        {{ $cell['full'] }}
    </span>
@elseif ($cell['truncated'])
    <details class="group inline-block max-w-full align-top">
        <summary class="cursor-pointer list-none break-words [&::-webkit-details-marker]:hidden">
            <span class="{{ $valueClass }}">{{ $cell['display'] }}</span>
            <span class="ms-1 whitespace-nowrap text-xs font-medium text-primary-600 underline-offset-2 hover:underline group-open:hidden">Show full value</span>
            <span class="ms-1 hidden whitespace-nowrap text-xs font-medium text-primary-600 underline-offset-2 hover:underline group-open:inline">Hide</span>
        </summary>
        <div class="mt-1 max-h-64 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-gray-50 p-2 text-xs text-gray-700 ring-1 ring-gray-950/5">{{ $cell['full'] }}</div>
    </details>
@else
    <span class="break-words {{ $valueClass }}">{{ $cell['display'] }}</span>
@endif
