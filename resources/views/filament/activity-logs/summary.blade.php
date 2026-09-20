{{--
    Headline at the top of the activity detail page: the same icon and
    sentence the timeline shows, at a larger size.
--}}
@php
    use App\Support\ActivityLogPresenter;

    $activity = $getRecord();
    $presenter = ActivityLogPresenter::for($activity);
    $target = $presenter->target();
    $createdAt = $activity->created_at?->copy()->timezone(config('app.timezone'));
@endphp

<div class="flex items-start gap-x-4">
    @include('filament.activity-logs.partials.event-icon', ['presenter' => $presenter, 'size' => 'lg'])

    <div class="min-w-0 flex-1">
        <p class="text-base leading-7 text-gray-700 dark:text-gray-300">
            <span class="font-semibold text-gray-950 dark:text-white">{{ $presenter->actorName() }}</span>
            {{ $presenter->action() }}
            @if ($target)
                <span class="font-semibold text-gray-950 dark:text-white">{{ $target }}</span>
            @endif
        </p>

        @if ($createdAt)
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                <time datetime="{{ $createdAt->toIso8601String() }}">
                    {{ $createdAt->diffForHumans() }} · {{ $createdAt->format('l, F j, Y g:i:s A') }}
                </time>
            </p>
        @endif
    </div>
</div>
