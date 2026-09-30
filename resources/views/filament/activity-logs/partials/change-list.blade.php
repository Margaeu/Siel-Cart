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

<dl class="mt-3 space-y-1.5 border-s-2 border-gray-100 ps-3 text-sm">
    @foreach ($visible as $change)
        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
            <dt class="text-gray-500">{{ $change['label'] }}:</dt>

            <dd class="flex min-w-0 flex-wrap items-baseline gap-x-1.5">
                @if ($mode === 'compare')
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['old'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'text-gray-500',
                    ])
                    <span aria-hidden="true" class="text-gray-400">→</span>
                    <span class="sr-only">changed to</span>
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['new'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-950',
                    ])
                @elseif ($mode === 'old')
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['old'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-700',
                    ])
                @else
                    @include('filament.activity-logs.partials.value', [
                        'cell' => $change['new'],
                        'sensitive' => $change['sensitive'],
                        'valueClass' => 'font-medium text-gray-950',
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
                    class="text-xs font-medium text-primary-600 underline-offset-2 hover:underline"
                >
                    +{{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('field', $hiddenCount) }} — view details
                </a>
            @else
                <span class="text-xs text-gray-500">
                    +{{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('field', $hiddenCount) }}
                </span>
            @endif
        </div>
    @endif
</dl>
