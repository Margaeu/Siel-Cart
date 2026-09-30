{{--
    The super admin dashboard's "Recent admin activity" card.

    Every word, icon, and colour comes from App\Support\ActivityLogPresenter —
    the same source the Activity Logs timeline reads — so this card can never
    describe an event differently from the resource, and it inherits the
    presenter's credential masking for free. This template only lays it out,
    and it reuses the timeline's own event-icon partial rather than restyling
    the circle.

    It deliberately shows no field-by-field diff: the sentence and the Details
    link are the glance; the resource is where changes are read.
--}}
@php
    use App\Filament\Resources\ActivityLogs\ActivityLogResource;
    use App\Filament\Resources\ActivityLogs\Widgets\RecentActivity;
    use App\Support\ActivityLogPresenter;

    $activities = $this->getActivities();
    $shortcuts = $this->getShortcuts();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-clipboard-document-list"
        heading="Recent admin activity"
        description="The {{ RecentActivity::LIMIT }} newest entries in the audit trail, newest first."
    >
        @if ($activities->isEmpty())
            <div class="flex flex-col items-center gap-3 px-6 py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-clipboard-document-list"
                    class="size-10 text-gray-400"
                />

                <div>
                    <p class="text-sm font-medium text-gray-950">
                        No activity recorded yet
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        Activity will appear here after administrators sign in or change tracked
                        records such as products, categories, orders, and users.
                    </p>
                </div>
            </div>
        @else
            <ul role="list" class="divide-y divide-gray-100">
                @foreach ($activities as $activity)
                    @php
                        $presenter = ActivityLogPresenter::for($activity);
                        $createdAt = $activity->created_at?->copy()->timezone(config('app.timezone'));
                        $target = $presenter->target();
                        $subjectTitle = in_array($presenter->event(), ActivityLogPresenter::CRUD_EVENTS, true)
                            ? $presenter->subjectTitle()
                            : null;
                    @endphp

                    <li class="flex items-start gap-x-3 py-3 first:pt-0 last:pb-0">
                        @include('filament.activity-logs.partials.event-icon', ['presenter' => $presenter, 'size' => 'md'])

                        <div class="min-w-0 flex-1">
                            <p class="text-sm leading-6 text-gray-700">
                                <span class="font-semibold text-gray-950">{{ $presenter->actorName() }}</span>
                                {{ $presenter->action() }}
                                @if ($target)
                                    <span class="font-semibold text-gray-950">{{ $target }}</span>
                                @endif
                                @if ($subjectTitle)
                                    <span class="text-gray-500">· {{ $subjectTitle }}</span>
                                @endif
                            </p>

                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                <span>{{ $presenter->eventLabel() }}</span>

                                @if ($createdAt)
                                    <span aria-hidden="true">·</span>
                                    <time
                                        datetime="{{ $createdAt->toIso8601String() }}"
                                        title="{{ $createdAt->format('l, F j, Y g:i:s A T') }}"
                                    >
                                        {{ $createdAt->diffForHumans() }}
                                        <span class="hidden sm:inline">· {{ $createdAt->format('M j, Y g:i A') }}</span>
                                    </time>
                                @endif
                            </div>
                        </div>

                        <a
                            href="{{ ActivityLogResource::getUrl('view', ['record' => $activity]) }}"
                            class="shrink-0 text-xs font-medium text-primary-600 underline-offset-2 hover:underline"
                        >
                            Details<span class="sr-only"> of {{ $presenter->sentence() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot name="footer">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                @foreach ($shortcuts as $shortcut)
                    <a
                        href="{{ $shortcut['url'] }}"
                        class="inline-flex items-center gap-x-1.5 text-sm font-medium text-primary-600 underline-offset-2 hover:underline"
                    >
                        <x-filament::icon :icon="$shortcut['icon']" class="size-4" />
                        {{ $shortcut['label'] }}
                    </a>
                @endforeach
            </div>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
