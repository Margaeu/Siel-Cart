<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\SielAvatarProvider;
use App\Filament\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('Siel Cart')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2.75rem')
            ->font(
                'Acumin Pro',
                url: asset('fonts/filament/filament/acumin-pro/index.css'),
                provider: LocalFontProvider::class,
            )
            //->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            // The desktop sidebar toggle ships as a chevron that flips direction with
            // the sidebar state. Both aliases get the hamburger so the control keeps one
            // icon either way, matching the mobile toggle (already Heroicon::OutlinedBars3).
            // The RTL aliases need no entry: the blade passes [rtlAlias, alias] and
            // IconManager::resolve() walks that list in order, so these cover RTL too.
            ->icons([
                PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => Heroicon::OutlinedBars3,
                PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => Heroicon::OutlinedBars3,
            ])
            ->defaultAvatarProvider(SielAvatarProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Only `primary` is overridden; every other slot (gray, info, success,
            // warning, danger) is left at Filament's default so the panel keeps the
            // stock Filament look with CLSU green as the accent. theme.css reads these
            // through --color-*-* rather than repeating the hex values.
            ->colors([
                'primary' => [
                    50 => '#f4f8ec',
                    100 => '#e5efcf',
                    200 => '#cddea8',
                    300 => '#acc875',
                    400 => '#88ac46',
                    500 => '#6e941f',
                    600 => '#557f13',
                    700 => '#436611',
                    800 => '#374f14',
                    900 => '#304415',
                    950 => '#172508',
                ],
            ])
            ->navigationGroups([
                'System Administration',
                'Shop Management',
                'Catalog',
                'Content Management',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            
            ->widgets([
                AccountWidget::class,
                //FilamentInfoWidget::class,
            ])
           
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('System Administration')
                    ->navigationIcon('heroicon-o-shield-check')
                    ->activeNavigationIcon('heroicon-o-shield-check')
                    ->navigationSort(20),
            ]);
    }
}
