{{-- Inline <style>, matching design-overview-banner-preview.blade.php. --}}
<style>
    .clsu-recent-designs__table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .clsu-recent-designs__table th {
        text-align: left;
        font-weight: 600;
        color: var(--gray-500);
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .clsu-recent-designs__table td {
        padding: 0.625rem 0.75rem;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
        color: var(--gray-950);
    }

    .clsu-recent-designs__table tr:last-child td {
        border-bottom: none;
    }

    .clsu-recent-designs__design {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .clsu-recent-designs__thumb {
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        overflow: hidden;
        flex-shrink: 0;
        background-color: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-400);
        border: 1px solid var(--gray-200);
    }

    .clsu-recent-designs__thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .clsu-recent-designs__swatch-pair {
        width: 100%;
        height: 100%;
        display: flex;
    }

    .clsu-recent-designs__swatch-pair span {
        flex: 1 1 50%;
    }
</style>

<x-filament-widgets::widget>
    <x-filament::section heading="Recently updated designs" description="The five most recently changed banners and color themes, combined.">
        @if ($rows->isEmpty())
            <x-filament::empty-state
                icon="heroicon-o-clock"
                heading="Nothing updated yet"
                description="Changes to promotional banners and color themes will show up here."
            />
        @else
            <div style="overflow-x: auto;">
                <table class="clsu-recent-designs__table">
                    <thead>
                        <tr>
                            <th scope="col">Design</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="design-overview-recent-{{ $row['key'] }}">
                                <td>
                                    <div class="clsu-recent-designs__design">
                                        <div class="clsu-recent-designs__thumb">
                                            @if ($row['kind'] === 'theme')
                                                <span class="clsu-recent-designs__swatch-pair">
                                                    <span style="background-color: {{ $row['swatch_colors'][0] }};"></span>
                                                    <span style="background-color: {{ $row['swatch_colors'][1] }};"></span>
                                                </span>
                                            @elseif ($row['image_url'])
                                                <img src="{{ $row['image_url'] }}" alt="">
                                            @elseif ($row['is_video'])
                                                <x-filament::icon icon="heroicon-o-play" class="h-4 w-4" />
                                            @else
                                                <x-filament::icon icon="heroicon-o-photo" class="h-4 w-4" />
                                            @endif
                                        </div>

                                        @if ($row['url'])
                                            <x-filament::link :href="$row['url']">
                                                {{ $row['label'] }}
                                            </x-filament::link>
                                        @else
                                            <span>{{ $row['label'] }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $row['type'] }}</td>
                                <td>
                                    <x-filament::badge :color="$row['status_color']">
                                        {{ $row['status'] }}
                                    </x-filament::badge>
                                </td>
                                <td>{{ $row['updated_at']->format('M d, Y - h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
