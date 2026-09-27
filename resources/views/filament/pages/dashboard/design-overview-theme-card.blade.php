{{-- Inline <style>, matching design-overview-banner-preview.blade.php. --}}
<style>
    .clsu-theme-card__swatches {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 0.75rem;
    }

    .clsu-theme-card__swatch {
        border-radius: 0.75rem;
        height: 3.5rem;
        border: 1px solid var(--gray-200);
    }

    .clsu-theme-card__swatch-label {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .clsu-theme-card__swatch-hex {
        font-family: ui-monospace, SFMono-Regular, monospace;
        font-size: 0.75rem;
        color: var(--gray-950);
    }

    .clsu-theme-card__preview {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 0.75rem;
        background-color: var(--gray-50);
        border: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .clsu-theme-card__preview-button {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .clsu-theme-card__preview-bar {
        flex: 1 1 4rem;
        min-width: 4rem;
        height: 0.5rem;
        border-radius: 9999px;
    }

    .clsu-theme-card__footer {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
    }
</style>

<x-filament-widgets::widget>
    <x-filament::section heading="Active color theme">
        @if ($theme)
            <x-slot name="afterHeader">
                <x-filament::badge color="success">In use</x-filament::badge>
            </x-slot>

            <p class="fi-section-header-description" style="margin-top: -0.5rem;">
                {{ $theme->name }}
            </p>

            <div class="clsu-theme-card__swatches">
                <div>
                    <div class="clsu-theme-card__swatch" style="background-color: {{ $theme->primary_color }};"></div>
                    <p class="clsu-theme-card__swatch-label">
                        Primary &middot; <span class="clsu-theme-card__swatch-hex">{{ $theme->primary_color }}</span>
                    </p>
                </div>

                <div>
                    <div class="clsu-theme-card__swatch" style="background-color: {{ $theme->secondary_color }};"></div>
                    <p class="clsu-theme-card__swatch-label">
                        Secondary &middot; <span class="clsu-theme-card__swatch-hex">{{ $theme->secondary_color }}</span>
                    </p>
                </div>
            </div>

            {{-- Illustrative only -- these colors are inline styles scoped to
                 this preview, not a live application of the theme to the
                 panel chrome. --}}
            <div class="clsu-theme-card__preview" aria-hidden="true">
                <span class="clsu-theme-card__preview-button" style="background-color: {{ $theme->primary_color }};">
                    Sample button
                </span>
                <span class="clsu-theme-card__preview-bar" style="background-color: {{ $theme->secondary_color }};"></span>
            </div>
        @else
            <x-slot name="afterHeader">
                <x-filament::badge color="warning">No active theme</x-filament::badge>
            </x-slot>

            {{-- Truthful about what the storefront actually falls back to --
                 see resources/views/partials/theme-styles.blade.php and
                 AdminPanelProvider's own $primaryPalette fallback. --}}
            <p class="fi-section-header-description" style="margin-top: -0.5rem;">
                No theme is currently marked active. The storefront and this panel fall back to the
                built-in CLSU colors &mdash; green <span class="clsu-theme-card__swatch-hex">#557F13</span>
                and gold <span class="clsu-theme-card__swatch-hex">#FFD801</span> &mdash; until a theme is activated.
            </p>
        @endif

        <div class="clsu-theme-card__footer">
            <x-filament::link :href="$manageThemesUrl" icon="heroicon-o-arrow-right" icon-position="after">
                Manage themes
            </x-filament::link>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
