@php
    // Site theme, managed in Admin → Design → Color Themes & Fonts.
    // Falls back to the legacy Setting-based values (Admin → Design → Site
    // Branding & Theme) if no Theme has been created/activated yet, and
    // finally to the site's original hardcoded defaults.
    $activeTheme = \App\Models\Theme::active()->first();

    $themePrimaryColor = $activeTheme?->primary_color
        ?? \App\Models\Setting::get('primary_color', '#1E6031');

    $themeSecondaryColor = $activeTheme?->secondary_color
        ?? \App\Models\Setting::get('secondary_color', '#E0A70D');

    $customFontName = $activeTheme?->custom_font_name;
    $customFontUrl = $activeTheme?->custom_font_url;
    $customFontFormat = match(strtolower(pathinfo($activeTheme?->custom_font_path ?? '', PATHINFO_EXTENSION))) {
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        default => null,
    };

    $themeFontFamily = $customFontName
        ?: ($activeTheme?->font_family ?? \App\Models\Setting::get('font_family', 'Inter'));

    // Whitelisted against the fonts offered in the admin form, so this is
    // always a known-safe value for the bunny fonts URL.
    $bunnyFontSlugs = [
        'Inter' => 'inter:400,500,600,700',
        'Poppins' => 'poppins:400,500,600,700',
        'Roboto' => 'roboto:400,500,700',
        'Nunito' => 'nunito:400,500,600,700',
        'Merriweather' => 'merriweather:400,700',
        'Playfair Display' => 'playfair-display:400,500,600,700',
    ];
    $bunnyFontSlug = $bunnyFontSlugs[$themeFontFamily] ?? $bunnyFontSlugs['Inter'];
@endphp

@if($customFontUrl && $customFontFormat)
    {{-- Custom font uploaded to R2 via Admin → Design → Color Themes & Fonts --}}
    <style>
        @font-face {
            font-family: '{{ $customFontName }}';
            src: url('{{ $customFontUrl }}') format('{{ $customFontFormat }}');
            font-display: swap;
        }
    </style>
@else
    {{-- Preset font, served from Bunny Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family={{ $bunnyFontSlug }}&display=swap" rel="stylesheet" />
@endif

<style>
    /*
     * Site theme — driven by Admin → Design → Color Themes & Fonts.
     * Blade views reference these via Tailwind arbitrary values, e.g.
     * text-[var(--color-primary)], bg-[var(--color-secondary)].
     */
    :root {
        --color-primary: {{ $themePrimaryColor }};
        --color-primary-hover: color-mix(in srgb, {{ $themePrimaryColor }} 82%, black);
        --color-secondary: {{ $themeSecondaryColor }};
        --font-family: '{{ $themeFontFamily }}', sans-serif;
    }

    body {
        font-family: var(--font-family);
    }
</style>
