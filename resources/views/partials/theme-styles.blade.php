@php
    // Site theme, managed in Admin → Design → Color Themes.
    // Falls back to the site's original hardcoded defaults if no Theme has
    // been created/activated yet. (There used to be a second fallback to a
    // `settings` table, but nothing ever wrote to it, so every lookup only
    // ever returned the default below; the table, model and seeder are gone.)
    //
    // Colors are all a theme carries. Typography is deliberately NOT themeable:
    // the client fixed the site on Acumin Pro, so the themes table lost its
    // font_family / custom_font_* columns and this partial lost the preset
    // picker, the R2 custom-font upload and the Bunny Fonts <link> that served
    // them. The one typeface is self-hosted in resources/css/app.css, which
    // sets --font-sans and so covers every storefront surface through
    // Preflight's html rule with no font-family declaration needed here; it's
    // pinned separately for the Filament panel in AdminPanelProvider, so admin
    // and storefront read as one product.
    $activeTheme = \App\Models\Theme::active()->first();

    $themePrimaryColor = $activeTheme?->primary_color ?? '#1E6031';

    $themeSecondaryColor = $activeTheme?->secondary_color ?? '#E0A70D';
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
    }
</style>
