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

    <img
        src="{{ $banner->image_url }}"
        alt="{{ $interactive ? ($banner->title ?? config('app.name') . ' banner') : '' }}"
        class="h-full w-full object-cover"
        draggable="false"
    >

    @if($banner->title || $banner->subtitle)
        <div class="absolute inset-0 flex items-center bg-gradient-to-t from-black/65 via-black/15 to-transparent">
            <div class="mx-auto w-full max-w-7xl px-5 sm:px-8 lg:px-12">
                <div class="max-w-2xl text-white drop-shadow-sm">
                    @if($banner->title)
                        <h1 class="text-3xl font-bold tracking-[-0.035em] sm:text-4xl lg:text-6xl">
                            {{ $banner->title }}
                        </h1>
                    @endif

                    @if($banner->subtitle)
                        <p class="mt-3 text-base font-medium text-white/90 sm:text-lg lg:text-xl">
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
