{{--
    Photos and video a customer attached to a review. URLs come from the
    Review::photo_urls / video_url accessors, which resolve against the r2 disk.
    Each photo links to the full-size file so an admin can inspect it before
    approving.
--}}
@php
    $review = $getRecord();
    $photos = $review->photo_urls;
    $videoUrl = $review->video_url;
@endphp

@if (empty($photos) && ! $videoUrl)
    <p class="text-sm text-gray-500 dark:text-gray-400">The customer did not attach any photos or video.</p>
@else
    <div class="space-y-6">
        @if (! empty($photos))
            <div>
                <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">
                    Photos <span class="text-gray-500 dark:text-gray-400">({{ count($photos) }})</span>
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($photos as $index => $url)
                        <a
                            href="{{ $url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group block aspect-square overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                            title="Open photo {{ $index + 1 }} in a new tab"
                        >
                            <img
                                src="{{ $url }}"
                                alt="Review photo {{ $index + 1 }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-200 group-hover:scale-105"
                            >
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($videoUrl)
            <div>
                <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">Video</p>
                <video
                    src="{{ $videoUrl }}"
                    controls
                    preload="metadata"
                    class="max-h-96 w-full max-w-2xl rounded-lg bg-black ring-1 ring-gray-950/5 dark:ring-white/10"
                >
                    <a href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">Open the video</a>
                </video>
            </div>
        @endif
    </div>
@endif
