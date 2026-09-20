{{--
    One activity on the Activity Logs timeline (a ViewColumn in
    ActivityLogsTable). All wording and every value comes from
    App\Support\ActivityLogPresenter; this template only lays it out.
--}}
@php
    use App\Filament\Resources\ActivityLogs\ActivityLogResource;
    use App\Support\ActivityLogPresenter;

    $activity = $getRecord();
    $presenter = ActivityLogPresenter::for($activity);
    $changes = $presenter->changes();
    $createdAt = $activity->created_at?->copy()->timezone(config('app.timezone'));
    $target = $presenter->target();
    $subjectTitle = in_array($presenter->event(), ActivityLogPresenter::CRUD_EVENTS, true)
        ? $presenter->subjectTitle()
        : null;
    $extraDescription = $presenter->extraDescription();
    $ipAddress = $presenter->ipAddress();
    $detailsUrl = ActivityLogResource::getUrl('view', ['record' => $activity]);
@endphp

<div class="relative flex w-full gap-x-4 py-1">
    {{--
        The rail overshoots the entry's own box into the row padding above and
        below, so consecutive entries join into one line across the dividers;
        the icon sits on top of it with a solid background. The first and last
        rows are trimmed to the icon with CSS on the record wrapper, because in
        a content-layout table Filament's $getRowLoop() is the loop over layout
        components, not over records, so it cannot say which row this is.
    --}}
    <span
        aria-hidden="true"
        class="pointer-events-none absolute start-4 -top-6 -bottom-14 w-px -translate-x-1/2 bg-gray-200 rtl:translate-x-1/2 dark:bg-white/10 [.fi-ta-record:first-child_&]:top-5 [.fi-ta-record:last-child_&]:bottom-[calc(100%-1.25rem)]"
    ></span>

    <span class="relative z-10 shrink-0 rounded-full bg-white dark:bg-gray-900">
        @include('filament.activity-logs.partials.event-icon', ['presenter' => $presenter])
    </span>

    <div class="min-w-0 flex-1 pt-1">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
            <p class="min-w-0 text-sm leading-6 text-gray-700 dark:text-gray-300">
                <span class="font-semibold text-gray-950 dark:text-white">{{ $presenter->actorName() }}</span>
                {{ $presenter->action() }}
                @if ($target)
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $target }}</span>
                @endif
                @if ($subjectTitle)
                    <span class="text-gray-500 dark:text-gray-400">· {{ $subjectTitle }}</span>
                @endif
            </p>

            @if ($createdAt)
                <time
                    datetime="{{ $createdAt->toIso8601String() }}"
                    title="{{ $createdAt->format('l, F j, Y g:i:s A T') }}"
                    class="shrink-0 text-xs leading-5 text-gray-500 sm:pt-0.5 sm:text-end dark:text-gray-400"
                >
                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $createdAt->diffForHumans() }}</span>
                    <span class="sm:block">
                        <span class="sm:hidden" aria-hidden="true">·</span>
                        {{ $createdAt->format('M j, Y g:i:s A') }}
                    </span>
                </time>
            @endif
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
            <span>{{ $presenter->eventLabel() }}</span>
            @if (filled($activity->log_name))
                <span aria-hidden="true">·</span>
                <span>{{ \Illuminate\Support\Str::headline($activity->log_name) }} log</span>
            @endif
            @if ($ipAddress)
                <span aria-hidden="true">·</span>
                <span>IP {{ $ipAddress }}</span>
            @endif
        </div>

        @if ($extraDescription)
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $extraDescription }}</p>
        @endif

        @if ($changes !== [])
            @include('filament.activity-logs.partials.change-list', [
                'presenter' => $presenter,
                'changes' => $changes,
                'limit' => 5,
                'detailsUrl' => $detailsUrl,
            ])
        @endif
    </div>
</div>
