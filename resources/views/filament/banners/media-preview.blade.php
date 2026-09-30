{{--
    Banner media preview for the admin view page.

    ImageEntry cannot render a clip -- an MP4's URL is a real file but not one an
    <img> can draw -- so the infolist routes clips here instead. `controls` is the
    opposite choice from the storefront slide: an admin checking a clip needs to
    scrub and replay it, where a visitor gets decorative autoplay with no chrome.

    No autoplay, so opening the page is quiet. `preload="metadata"` fetches just
    enough for the first frame and duration rather than the whole file.
--}}
@php
    $banner = $getRecord();
@endphp

@if ($banner?->image_url)
    <video
        src="{{ $banner->image_url }}"
        class="w-full rounded-lg object-cover"
        style="height: 280px;"
        controls
        muted
        loop
        playsinline
        preload="metadata"
    ></video>
@else
    <span class="fi-in-placeholder text-sm text-gray-400">
        No clip uploaded
    </span>
@endif
