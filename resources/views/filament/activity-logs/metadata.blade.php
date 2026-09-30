{{--
    Fallback list of any other properties stored with the activity. Nested
    values arrive pretty-printed and with credential keys already masked by
    ActivityLogPresenter::metadata().
--}}
@php
    use App\Support\ActivityLogPresenter;

    $rows = ActivityLogPresenter::for($getRecord())->metadata();
@endphp

<dl class="divide-y divide-gray-100 text-sm">
    @foreach ($rows as $row)
        <div class="grid gap-1 py-2.5 sm:grid-cols-4 sm:gap-4">
            <dt class="font-medium text-gray-950">{{ $row['label'] }}</dt>
            <dd class="min-w-0 sm:col-span-3">
                @if (str_contains($row['value'], "\n"))
                    <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-700 ring-1 ring-gray-950/5">{{ $row['value'] }}</pre>
                @else
                    <span class="break-words text-gray-700">{{ $row['value'] }}</span>
                @endif
            </dd>
        </div>
    @endforeach
</dl>
