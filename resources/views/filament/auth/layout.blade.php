@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $isLogin = $livewire instanceof \App\Filament\Pages\Auth\Login;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="clsu-auth-layout">
        <a href="#fi-main-content" class="fi-skip-link fi-sr-only">Skip to authentication form</a>

        <x-customer-auth-header :admin="true" />

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <div class="clsu-auth-stage">
            <div class="clsu-auth-column">
                <main id="fi-main-content" tabindex="-1" class="fi-simple-main clsu-auth-card">
                    <div class="clsu-auth-eyebrow">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedShieldCheck" class="clsu-auth-eyebrow__icon" />
                        <span>Administrator access</span>
                    </div>

                    {{ $slot }}
                </main>

                <nav class="clsu-auth-navigation" aria-label="Authentication navigation">
                    <a href="{{ $isLogin ? route('login') : filament()->getLoginUrl() }}">
                        <span aria-hidden="true">&larr;</span>
                        {{ $isLogin ? 'Customer login' : 'Back to admin login' }}
                    </a>
                </nav>
            </div>
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
