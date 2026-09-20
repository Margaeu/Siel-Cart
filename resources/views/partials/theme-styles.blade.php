@php
    // Site theme, managed in Admin → Design → Color Themes.
    // Falls back to the legacy Setting-based values (Admin → Design → Site
    // Branding & Theme) if no Theme has been created/activated yet, and
    // finally to the site's original hardcoded defaults.
    //
    // Colors are all a theme carries. Typography is deliberately NOT themeable:
    // the client fixed the site on Acumin Pro, so the themes table lost its
    // font_family / custom_font_* columns and this partial lost the preset
    // picker, the R2 custom-font upload and the Bunny Fonts <link> that served
    // them. The one typeface is self-hosted in resources/css/app.css (which
    // every storefront surface loads) and pinned for the Filament panel in
    // AdminPanelProvider, so admin and storefront read as one product. Putting
    // a font back under admin control means restoring all three layers, not
    // just this file.
    $activeTheme = \App\Models\Theme::active()->first();

    $themePrimaryColor = $activeTheme?->primary_color
        ?? \App\Models\Setting::get('primary_color', '#1E6031');

    $themeSecondaryColor = $activeTheme?->secondary_color
        ?? \App\Models\Setting::get('secondary_color', '#E0A70D');
@endphp

<style>
    /*
     * Site theme — driven by Admin → Design → Color Themes.
     * Blade views reference these via Tailwind arbitrary values, e.g.
     * text-[var(--color-primary)], bg-[var(--color-secondary)].
     */
    :root {
        --color-primary: {{ $themePrimaryColor }};
        --color-primary-hover: color-mix(in srgb, {{ $themePrimaryColor }} 82%, black);
        --color-secondary: {{ $themeSecondaryColor }};
        --font-family: 'Acumin Pro', sans-serif;
    }

    body {
        font-family: var(--font-family);
    }
</style>
