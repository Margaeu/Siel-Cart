<?php

namespace App\Filament\Pages\Dashboard\Widgets;

use App\Filament\Resources\Banners\BannerResource;
use App\Models\Banner;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Currently on the storefront" -- a selectable preview of the active
 * banner carousel, read through the same scope and order as the storefront
 * hero (App\Livewire\HomePage::render()) so the two can never disagree.
 * Dashboard preview only: selecting a thumbnail never touches is_active or
 * sort_order, it only moves $selectedIndex.
 */
class DesignOverviewBannerPreview extends Widget
{
    protected string $view = 'filament.pages.dashboard.design-overview-banner-preview';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    public int $selectedIndex = 0;

    // Eager, matching ActivityLogStats and RecentActivity. Widget::$isLazy
    // defaults to true, which would make each of the four Design Overview
    // widgets its own Livewire round trip after first paint -- five HTTP
    // requests to render a page whose entire cost is a handful of trivial
    // queries, and each of those requests re-pays the panel's auth and
    // role/permission lookups as well. Measured: eager renders the whole
    // dashboard in one request.
    protected static bool $isLazy = false;

    /**
     * The active carousel for the current request only.
     *
     * Deliberately a private, non-Livewire property rather than a public one or
     * a #[Computed(persist: true)] cache: a public Eloquent collection would be
     * serialised into the component payload and handed back from the browser,
     * and a persisted cache would outlive the request, so a banner another admin
     * switched off would keep rendering here until the cache expired. Livewire
     * constructs a fresh component instance per request, so this is empty again
     * on every mount and every action -- which is what makes selectBanner()'s
     * bounds check and getViewData()'s render share one query instead of two,
     * while both still see state no older than this request.
     */
    private ?Collection $banners = null;

    public static function canView(): bool
    {
        $user = Filament::auth()?->user();

        return $user instanceof User && $user->isDesignOnlyAdmin();
    }

    public function banners(): Collection
    {
        return $this->banners ??= Banner::active()->ordered()->get();
    }

    public function selectBanner(int $index): void
    {
        if (! $this->banners()->has($index)) {
            return;
        }

        $this->selectedIndex = $index;
    }

    protected function getViewData(): array
    {
        $banners = $this->banners();

        // A banner could be deactivated by another admin between page loads;
        // fall back to the first slide rather than rendering nothing.
        if (! $banners->has($this->selectedIndex)) {
            $this->selectedIndex = 0;
        }

        return [
            'banners' => $banners,
            'selected' => $banners->get($this->selectedIndex),
            'manageBannersUrl' => BannerResource::getUrl('index'),
        ];
    }
}
