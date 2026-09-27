@php
    // Every product, category, banner and review image is served from
    // Cloudflare R2, which is a different origin to the app. Without a hint the
    // browser only begins that origin's DNS lookup and TLS handshake when it
    // meets the first <img> -- and on Azure (Malaysia West) that is a handshake
    // the visitor waits through before any image starts arriving. Issued here,
    // it overlaps with parsing the rest of the head.
    //
    // Built from the disk's configured URL rather than hardcoded, so it follows
    // CLOUDFLARE_R2_PUBLIC_URL when the bucket moves to a custom domain, and
    // emits nothing at all on a machine with no R2 configured.
    $r2Url = (string) config('filesystems.disks.r2.url');
    $r2Parts = $r2Url !== '' ? parse_url($r2Url) : false;

    $r2Origin = is_array($r2Parts) && isset($r2Parts['scheme'], $r2Parts['host'])
        ? $r2Parts['scheme'].'://'.$r2Parts['host'].(isset($r2Parts['port']) ? ':'.$r2Parts['port'] : '')
        : null;
@endphp

@if ($r2Origin)
    {{--
        No `crossorigin` attribute. These images are fetched by plain <img>
        tags, which are not anonymous requests, and a crossorigin preconnect
        opens a separate CORS connection that those loads never reuse -- it
        would cost an extra handshake rather than save one. Fonts, which do
        need it, are self-hosted and same-origin.

        dns-prefetch repeats the hint for anything that ignores preconnect;
        browsers that honour preconnect skip the duplicate.
    --}}
    <link rel="preconnect" href="{{ $r2Origin }}">
    <link rel="dns-prefetch" href="{{ $r2Origin }}">
@endif
