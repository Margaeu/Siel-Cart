<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\SielAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewBannerPreview;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewRecentDesigns;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewStats;
use App\Filament\Pages\Dashboard\Widgets\DesignOverviewThemeCard;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Theme;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Same theme row the storefront reads in
        // resources/views/partials/theme-styles.blade.php, so Admin -> Design ->
        // Color Themes drives both halves of the site. Wrapped in a try/catch
        // because this closure is resolved lazily by Filament — including
        // during `route:cache`/`config:cache` before migrations have run,
        // when the `themes` table may not exist yet.
        try {
            $activeTheme = Theme::activeCached();
        } catch (\Throwable) {
            $activeTheme = null;
        }

        // The hand-picked default palette below is what the panel always used
        // before theming existed. Keeping it as the no-theme-configured
        // fallback (instead of running it through Color::hex()) means the
        // panel looks pixel-identical until an admin actually activates a
        // theme with a different primary color.
        $primaryPalette = $activeTheme
            ? Color::hex($activeTheme->primary_color)
            : [
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
            ];

        // Filament regenerates shades on its own lightness curve. Anchor the
        // base shade to the saved hex so the panel matches the storefront.
        $primaryColorRaw = $activeTheme?->primary_color ?? '#557f13';
        $primaryPalette[600] = $primaryColorRaw;

        // Mix accent shades from the actual color rather than regenerating its
        // hue as a different gold. Keep the base button shade exact as well.
        $secondaryColorRaw = $activeTheme?->secondary_color ?? '#FFD801';
        $secondaryPalette = Color::generateV3Palette($secondaryColorRaw);
        $secondaryPalette[600] = $secondaryColorRaw;

        // Filament treats `primary` as the default for routine actions such as
        // Sign in, Add, Create, Save and Edit. In Siel Cart, green is reserved
        // for the institutional masthead while the active theme's secondary
        // colour is the call-to-action accent. Apply that convention once so
        // current and future routine actions stay consistent. Explicit
        // semantic colours (danger, warning, success, info and gray) are left
        // intact, and a page may still override this after Action::make().
        //
        // 'profile' and 'logout' are excluded: those are Filament's built-in
        // user-menu items (HasUserMenu::getUserAccountMenuItem() /
        // getUserLogoutMenuItem()), not routine CRUD actions, and recolouring
        // them turned "System Admin" / "Sign out" gold instead of the
        // neutral gray-700 every other dropdown item uses.
        Action::configureUsing(
            function (Action $action): void {
                if (in_array($action->getName(), ['profile', 'logout'], true)) {
                    return;
                }

                if (in_array($action->getColor(), [null, 'primary'], true)) {
                    $action->color('secondary');
                }
            },
            isImportant: true,
        );

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Keep the browser shell and assets while navigating panel pages.
            ->spa()
            ->login(Login::class)
            // Needed for administrator invitations: a new admin's "Set your password"
            // link opens the reset page registered here. The request page is the
            // enumeration-safe subclass; the reset page says "Set" instead of
            // "Reset" when opened from an invitation. Registration stays off —
            // admins are only ever created by a super admin from the Users resource.
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            // Named rather than left to auth.defaults.passwords so an env override
            // of the default broker cannot point the panel at `customers`.
            ->authPasswordBroker('users')
            ->brandName('Siel Cart')
            ->brandLogo(fn () => view('filament.admin.brand'))
            // The brand view is sized off this: Filament wraps it in a div with
            // `height: <this>`, and the seal inside stretches to 100% of it. 3.5rem
            // matches the storefront header's seal; it needs the taller topbar that
            // theme.css sets via --topbar-height, so change the two together.
            ->brandLogoHeight('3.5rem')
            ->font(
                'Acumin Pro',
                url: asset('fonts/filament/filament/acumin-pro/index.css'),
                provider: LocalFontProvider::class,
            )
            // ->databaseNotifications()
            // Filament turns the topbar search on by itself as soon as any resource is
            // globally searchable (a `$recordTitleAttribute` is enough — CustomerResource
            // and others set one). Admins navigate through the sidebar groups instead, so
            // the field is switched off at the panel rather than by stripping the
            // attribute off every resource, which would also break relation-manager and
            // select-field record labels.
            ->globalSearch(false)
            // The panel is light-only: no light/dark/system switcher in the user menu.
            // darkMode(false) is what removes it — themeSwitcher(false) alone would hide
            // the control while still letting a stored `theme` render the panel dark.
            // It also has the layout write localStorage.theme = 'light' on every load, so
            // an admin who had already picked dark is reset instead of stuck there.
            ->darkMode(false)
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
            // `primary` and `secondary` are the only overrides; every other slot
            // (gray, info, success, warning, danger) is left at Filament's default
            // so the panel keeps the stock Filament look, accented with whichever
            // theme is active — CLSU green/gold when none is. theme.css reads
            // these through --color-*-* rather than repeating hex values.
            ->colors([
                'primary' => $primaryPalette,
                'secondary' => $secondaryPalette,
            ])
            ->navigationGroups([
                'System Administration',
                'Shop Management',
                'Catalog',
                'Content Management',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // One dashboard route; widgets are selected by role and permission.
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')

            ->widgets([
                AccountWidget::class,
                DesignOverviewStats::class,
                DesignOverviewBannerPreview::class,
                DesignOverviewThemeCard::class,
                DesignOverviewRecentDesigns::class,
                // FilamentInfoWidget::class,
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
            // Refreshes the Orders/Reviews/Reports sidebar badges when a new
            // order, review, or report comes in. Only mounted for a signed-in
            // admin who can see at least one of them — BODY_END also renders
            // on the login page, where there is nothing to poll.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth('web')->check()
                    && (OrderResource::canViewAny() || ReviewResource::canViewAny() || ReportResource::canViewAny())
                    ? Blade::render('@livewire(\App\Livewire\Admin\NavigationBadgePoller::class)')
                    : '',
            )
            // Makes the theme's raw primary hex available as --color-primary-raw
            // on every panel page, alongside Filament's generated --color-primary-*
            // shades. Brand surfaces use the raw value to match the storefront.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<style>:root {--color-primary-raw: {{ $color }};}</style>',
                    ['color' => $primaryColorRaw],
                ),
            )
            // Livewire restores old HTML for browser Back/Forward navigation.
            // Lists, record pages, and dashboard widgets can all become stale
            // after a create, update, delete, restore, or other admin action.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.admin.refresh-admin-history', [
                    'panelPath' => '/'.trim($panel->getPath(), '/'),
                ]),
            )
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('System Administration')
                    ->navigationIcon('heroicon-o-shield-check')
                    ->activeNavigationIcon('heroicon-o-shield-check')
                    ->navigationSort(20),
            ]);
    }
}
