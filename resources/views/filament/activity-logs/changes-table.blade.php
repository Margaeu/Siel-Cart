{{--
    Field-by-field comparison on the activity detail page. Shows Before and
    After for an update, or a single Value column for a creation/deletion.
    Values are already formatted and masked by ActivityLogPresenter.
--}}
@php
    use App\Support\ActivityLogPresenter;

    $presenter = ActivityLogPresenter::for($getRecord());
    $changes = $presenter->changes();
    $mode = $presenter->changeMode();
@endphp

<div class="overflow-x-auto">
    <table class="w-full min-w-[32rem] table-fixed divide-y divide-gray-200 text-start text-sm">
        <thead>
            <tr class="text-xs font-medium uppercase tracking-wide text-gray-500">
                <th scope="col" class="w-1/4 py-2 pe-4 text-start">Field</th>
                @if ($mode === 'compare')
                    <th scope="col" class="py-2 pe-4 text-start">Before</th>
                    <th scope="col" class="py-2 text-start">After</th>
                @else
                    <th scope="col" class="py-2 text-start">Value</th>
                @endif
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
            @foreach ($changes as $change)
                <tr class="align-top">
                    <th scope="row" class="py-2.5 pe-4 text-start font-medium text-gray-950">
                        {{ $change['label'] }}
                    </th>

                    @if ($mode === 'compare')
                        <td class="py-2.5 pe-4">
                            @include('filament.activity-logs.partials.value', [
                                'cell' => $change['old'],
                                'sensitive' => $change['sensitive'],
                                'valueClass' => 'text-gray-500',
                            ])
                        </td>
                        <td class="py-2.5">
                            @include('filament.activity-logs.partials.value', [
                                'cell' => $change['new'],
                                'sensitive' => $change['sensitive'],
                                'valueClass' => 'font-medium text-gray-950',
                            ])
                        </td>
                    @else
                        <td class="py-2.5">
                            @include('filament.activity-logs.partials.value', [
                                'cell' => $mode === 'old' ? $change['old'] : $change['new'],
                                'sensitive' => $change['sensitive'],
                                'valueClass' => 'text-gray-950',
                            ])
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
