{{--
    Compact "Field: old → new" rows under a timeline entry.
    Expects: $presenter (App\Support\ActivityLogPresenter), $changes (array),
    optional $limit (int|null) and $detailsUrl (string|null) for the overflow link.
--}}
@php
    $limit ??= null;
    $detailsUrl ??= null;
    $mode = $presenter->changeMode();
    $visible = $limit ? array_slice($changes, 0, $limit) : $changes;
    $hiddenCount = count($changes) - count($visible);
@endphp

<dl class="mt-3 space-y-1.5 border-s-2 border-gray-100 ps-3 text-sm dark:border-white/10">
    @foreach ($visible as $change)
        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
            <dt class="text-gray-500 dark:text-gray-400">{{ $change['label'] }}:</dt>

            <dd class="flex min-w-0 flex-wrap items-baseline gap-x-1.5">
                @if ($mode === 'compare')
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['old'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'text-gray-500 dark:text-gray-400',
                    ])
                    <span aria-hidden="true" class="text-gray-400 dark:text-gray-500">→</span>
                    <span class="sr-only">changed to</span>
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['new'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-950 dark:text-white',
                    ])
                @elseif ($mode === 'old')
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['old'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-700 dark:text-gray-300',
                    ])
                @else
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['new'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-950 dark:text-white',
                    ])
                @endif
            </dd>
        </div>
    @endforeach

    @if ($hiddenCount > 0)
        <div>
            @if ($detailsUrl)
                <a
                    href="{{ $detailsUrl }}"
                    class="text-xs font-medium text-primary-600 underline-offset-2 hover:underline dark:text-primary-400"
                >
                    +{{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('field', $hiddenCount) }} — view details
                </a>
            @else
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    +{{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('field', $hiddenCount) }}
                </span>
            @endif
        </div>
    @endif
</dl>
