@php
    // Same source of truth as the storefront header
    // (components/layouts/front-end-layout.blade.php) and the customer auth brand,
    // so the admin panel carries the same name the shop does. The tagline is the
    // one thing that differs: this is the back office, not the store.
    $siteName = \App\Models\Setting::get('site_name') ?: config('app.name', 'Siel Cart');
@endphp

{{--
    Styles are inline / in a scoped <style> rather than in
    resources/css/filament/admin/theme.css on purpose: the panel theme is compiled
    through Vite, so putting the rules there would make the masthead depend on a
    build step. The only thing that needs a class is the seal swap, which cannot
    be expressed with an inline style.

    Two seals ship with the app: LOGO.png is the white cut and Logo_Black.png the
    black one. Which is right depends on the surface behind it, and the panel
    renders this same view onto two different ones:

      - the topbar, which theme.css paints CLSU green in BOTH light and dark mode
        (it matches the storefront header) -> always the white cut;
      - the mobile sidebar drawer header and the login card, which stay white in
        light mode and near-black in dark -> black cut, flipping with the theme.

    Hence the swap keys off `.fi-topbar` first and `.dark` second. Filament hides
    the sidebar header at lg and up (`.fi-body-has-topbar .fi-sidebar-header` is
    `lg:hidden`), so the two never show at once.
--}}
<style>
    .clsu-brand__seal--dark { display: none; }
    .dark .clsu-brand__seal--light { display: none; }
    .dark .clsu-brand__seal--dark { display: block; }

    /* Must stay last: same specificity as the .dark rules, so source order wins. */
    .fi-topbar .clsu-brand__seal--light { display: none; }
    .fi-topbar .clsu-brand__seal--dark { display: block; }
</style>

<div
    aria-label="{{ $siteName }} admin dashboard"
    style="display: flex; min-width: 0; height: 100%; align-items: center; gap: 0.25rem; color: inherit;"
>
    <img
        src="{{ asset('images/Logo_Black.png') }}"
        alt="Central Luzon State University seal"
        class="clsu-brand__seal--light"
        style="height: 100%; width: auto; flex: none; object-fit: contain;"
    >
    <img
        src="{{ asset('images/LOGO.png') }}"
        alt="Central Luzon State University seal"
        class="clsu-brand__seal--dark"
        style="height: 100%; width: auto; flex: none; object-fit: contain;"
    >

    <span
        aria-hidden="true"
        style="height: 2.25rem; width: 1px; flex: none; background-color: currentColor; opacity: 0.25;"
    ></span>

    {{-- Type scale mirrors the storefront masthead: name at text-xl, tagline at text-xs. --}}
    <span style="min-width: 0; padding-left: 0.5rem;">
        <span style="display: block; overflow: hidden; font-size: 1.25rem; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap;">
            {{ Str::upper($siteName) }}
        </span>
        <span style="display: block; margin-top: 0.125rem; overflow: hidden; font-size: 0.75rem; font-weight: 500; line-height: 1.2; opacity: 0.75; text-overflow: ellipsis; white-space: nowrap;">
            Admin dashboard
        </span>
    </span>
</div>
