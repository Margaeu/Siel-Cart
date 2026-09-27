@props([
    'banner',
    'interactive' => false,
    'index' => null,
])

@if($interactive && $banner->link_url)
    <a
        href="{{ $banner->link_url }}"
        class="relative block h-full w-full"
        x-bind:tabindex="active === {{ $index }} ? 0 : -1"
        draggable="false"
    >
@else
    <div class="relative h-full w-full">
@endif

    @if($banner->is_video)
        {{--
            A clip slide. `muted` is not optional: every browser blocks autoplay
            with sound, so without it the clip silently never starts. `playsinline`
            stops iOS Safari hijacking the slide into its native fullscreen player,
            and the carousel rotates on a 6s timer, so `loop` keeps a shorter clip
            from freezing on its last frame while the slide is still showing.

            No `controls`: this is decorative promotional media inside a carousel
            that already owns the pointer for swipe and the arrows for navigation,
            and a control bar would sit on top of both.

            No `src` and no `autoplay`: the carousel owns playback, and it hands
            this element its URL only while the slide is the one on screen (see
            syncVideos() in livewire/home-page.blade.php, keyed on the same
            `data-slide-position` the track stamps on each wrapper).

            This used to be `src` + `autoplay` + `preload="metadata"`, with a
            comment claiming the preload hint kept the clips from being pulled in
            full. It does not: `autoplay` overrides `preload`, so every slide in
            the DOM from first paint -- all the real ones plus the two cloned edge
            slides -- downloaded and decoded its whole clip on every homepage
            visit. With clips capped at 10 MB and the media served from R2 that
            was the heaviest thing on the site, and the simultaneous decodes were
            worst on exactly the phones least able to absorb them. `preload="none"`
            is honest now that nothing autoplays.

            The primary-colour background matches the reduced-motion fallback
            below, so an element that has not been handed its URL yet reads as a
            brand-coloured slide rather than an empty black box.
        --}}
        <video
            data-banner-video
            data-src="{{ $banner->image_url }}"
            class="h-full w-full bg-[var(--color-primary)] object-cover motion-reduce:hidden"
            muted
            loop
            playsinline
            preload="none"
            aria-hidden="true"
            draggable="false"
        ></video>

        {{--
            prefers-reduced-motion: the <video> above is hidden by
            motion-reduce:hidden and this takes its place. A clip is motion the
            visitor has asked not to be shown, and there is no still to fall back
            to -- the banner is the clip -- so the slide keeps the carousel's own
            background rather than collapsing to zero height and pulling the track
            out of alignment.
        --}}
        <div class="hidden h-full w-full bg-[var(--color-primary)] motion-reduce:block" aria-hidden="true"></div>
    @else
        <img
            src="{{ $banner->image_url }}"
            alt="{{ $interactive ? ($banner->title ?? config('app.name') . ' banner') : '' }}"
            class="h-full w-full object-cover"
            draggable="false"
        >
    @endif

    @if($banner->title || $banner->subtitle)
        <div class="absolute inset-0 flex items-center bg-gradient-to-t from-black/65 via-black/15 to-transparent">
            <div class="mx-auto w-full max-w-7xl px-5 sm:px-8 lg:px-12">
                <div class="max-w-2xl text-white drop-shadow-sm">
                    @if($banner->title)
                        <h1 class="text-2xl font-bold tracking-[-0.035em] sm:text-3xl lg:text-5xl">
                            {{ $banner->title }}
                        </h1>
                    @endif

                    @if($banner->subtitle)
                        <p class="mt-3 text-sm font-medium text-white/90 sm:text-base lg:text-lg">
                            {{ $banner->subtitle }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

@if($interactive && $banner->link_url)
    </a>
@else
    </div>
@endif
