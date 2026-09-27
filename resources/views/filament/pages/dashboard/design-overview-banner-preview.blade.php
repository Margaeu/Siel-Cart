{{--
    Inline <style>, not theme.css, so this renders correctly without a Vite
    build -- the same reason app/Filament/Widgets/InventoryManagement's view
    does it this way. Classes are scoped with a clsu-banner-preview prefix so
    nothing here leaks onto the rest of the panel.
--}}
<style>
    .clsu-banner-preview__frame {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 7;
        background-color: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 0.75rem;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .clsu-banner-preview__frame img,
    .clsu-banner-preview__frame video {
        width: 100%;
        height: 100%;
        /* Contain, not cover: this is a reference preview for the admin,
           so the whole banner should stay visible and undistorted rather
           than being cropped the way the storefront hero crops it. */
        object-fit: contain;
    }

    .clsu-banner-preview__missing {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.375rem;
        color: var(--gray-400);
        font-size: 0.8125rem;
    }

    .clsu-banner-preview__thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .clsu-banner-preview__thumb {
        position: relative;
        width: 4.5rem;
        height: 3rem;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 2px solid transparent;
        background-color: var(--gray-100);
        padding: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .clsu-banner-preview__thumb:hover {
        border-color: var(--gray-300);
    }

    .clsu-banner-preview__thumb--active {
        border-color: var(--primary-600);
    }

    .clsu-banner-preview__thumb:focus-visible {
        outline: 2px solid var(--primary-600);
        outline-offset: 2px;
    }

    .clsu-banner-preview__thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .clsu-banner-preview__thumb-icon {
        color: var(--gray-400);
    }

    .clsu-banner-preview__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
        font-size: 0.8125rem;
        color: var(--gray-500);
    }
</style>

<x-filament-widgets::widget>
    <x-filament::section heading="Currently on the storefront">
        @if ($banners->isEmpty())
            <x-filament::empty-state
                icon="heroicon-o-photo"
                heading="No banners are active right now"
                description="Turn a banner on from Promotional Banners to feature it on the storefront home page."
            >
                <x-slot name="footer">
                    <x-filament::button tag="a" :href="$manageBannersUrl" color="gray" icon="heroicon-o-photo">
                        Manage banners
                    </x-filament::button>
                </x-slot>
            </x-filament::empty-state>
        @else
            <div class="clsu-banner-preview__frame">
                @if ($selected->is_video)
                    @if ($selected->image_url)
                        <video
                            src="{{ $selected->image_url }}"
                            muted
                            loop
                            autoplay
                            playsinline
                            preload="metadata"
                            aria-label="Banner {{ $selectedIndex + 1 }} preview clip"
                        ></video>
                    @else
                        <div class="clsu-banner-preview__missing">
                            <x-filament::icon icon="heroicon-o-video-camera-slash" class="h-8 w-8" />
                            Clip file is missing
                        </div>
                    @endif
                @elseif ($selected->image_url)
                    <img src="{{ $selected->image_url }}" alt="Banner {{ $selectedIndex + 1 }} preview">
                @else
                    <div class="clsu-banner-preview__missing">
                        <x-filament::icon icon="heroicon-o-photo" class="h-8 w-8" />
                        Image file is missing
                    </div>
                @endif
            </div>

            @if ($banners->count() > 1)
                <div class="clsu-banner-preview__thumbs" role="listbox" aria-label="Active banners, in storefront order">
                    @foreach ($banners as $index => $banner)
                        <button
                            type="button"
                            wire:key="design-overview-banner-thumb-{{ $banner->id }}"
                            wire:click="selectBanner({{ $index }})"
                            role="option"
                            aria-selected="{{ $index === $selectedIndex ? 'true' : 'false' }}"
                            aria-label="Show banner {{ $index + 1 }} of {{ $banners->count() }}"
                            class="clsu-banner-preview__thumb {{ $index === $selectedIndex ? 'clsu-banner-preview__thumb--active' : '' }}"
                        >
                            @if (! $banner->is_video && $banner->image_url)
                                <img src="{{ $banner->image_url }}" alt="">
                            @elseif ($banner->is_video)
                                <span class="clsu-banner-preview__thumb-icon">
                                    <x-filament::icon icon="heroicon-o-play" class="h-4 w-4" />
                                </span>
                            @else
                                <span class="clsu-banner-preview__thumb-icon">
                                    <x-filament::icon icon="heroicon-o-photo" class="h-4 w-4" />
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="clsu-banner-preview__footer">
                <span>
                    Banner {{ $selectedIndex + 1 }} of {{ $banners->count() }} &middot; Display order {{ $selected->sort_order }}
                </span>

                <x-filament::link :href="$manageBannersUrl" icon="heroicon-o-arrow-right" icon-position="after">
                    Manage banners
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
