@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="clsu-auth-layout">
        <a href="#fi-main-content" class="fi-skip-link fi-sr-only">Skip to authentication form</a>

        <x-customer-auth-header :admin="true" />

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <div class="clsu-auth-stage">
            <div class="clsu-auth-column">
                <main id="fi-main-content" tabindex="-1" class="fi-simple-main clsu-auth-card">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
