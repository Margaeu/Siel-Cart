<?php

namespace App\Livewire\Admin;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Reviews\ReviewResource;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Keeps the Orders/Reviews/Reports sidebar badges current without a page
 * reload.
 *
 * Filament renders navigation badges once, when the sidebar renders, so a
 * new order, review, or report would not show up until the admin clicked
 * somewhere. Polling the whole sidebar would re-render every navigation item
 * on every tick; instead this invisible component polls just the three
 * counts and only asks the sidebar to refresh (Filament's own
 * `refresh-sidebar` listener) when one of them actually changed.
 *
 * Mounted at BODY_END by AdminPanelProvider.
 */
class NavigationBadgePoller extends Component
{
    public ?string $ordersBadge = null;

    public ?string $reviewsBadge = null;

    public ?string $reportsBadge = null;

    public function mount(): void
    {
        $this->ordersBadge = OrderResource::canViewAny() ? OrderResource::getNavigationBadge() : null;
        $this->reviewsBadge = ReviewResource::canViewAny() ? ReviewResource::getNavigationBadge() : null;
        $this->reportsBadge = ReportResource::canViewAny() ? ReportResource::getNavigationBadge() : null;
    }

    public function check(): void
    {
        // A poll can outlive the session (logout in another tab, expiry), and
        // a role can lose access mid-session; either way stop reporting.
        if (! auth('web')->check()) {
            return;
        }

        $changed = false;

        if (OrderResource::canViewAny()) {
            $badge = OrderResource::getNavigationBadge();

            if ($badge !== $this->ordersBadge) {
                $this->ordersBadge = $badge;
                $changed = true;
            }
        }

        if (ReviewResource::canViewAny()) {
            $badge = ReviewResource::getNavigationBadge();

            if ($badge !== $this->reviewsBadge) {
                $this->reviewsBadge = $badge;
                $changed = true;
            }
        }

        if (ReportResource::canViewAny()) {
            $badge = ReportResource::getNavigationBadge();

            if ($badge !== $this->reportsBadge) {
                $this->reportsBadge = $badge;
                $changed = true;
            }
        }

        if ($changed) {
            $this->dispatch('refresh-sidebar');
        }
    }

    public function render(): View
    {
        // wire:poll is throttled by Livewire while the tab is in the background.
        return view('livewire.admin.navigation-badge-poller');
    }
}
