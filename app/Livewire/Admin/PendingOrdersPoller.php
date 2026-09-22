<?php

namespace App\Livewire\Admin;

use App\Filament\Resources\Orders\OrderResource;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Keeps the "Orders" sidebar badge current without a page reload.
 *
 * Filament renders navigation badges once, when the sidebar renders, so a
 * new checkout would not show up until the admin clicked somewhere. Polling
 * the whole sidebar would re-render every navigation item on every tick;
 * instead this invisible component polls just the count and only asks the
 * sidebar to refresh (Filament's own `refresh-sidebar` listener) when the
 * number actually changed.
 *
 * Mounted at BODY_END by AdminPanelProvider.
 */
class PendingOrdersPoller extends Component
{
    public ?string $badge = null;

    public function mount(): void
    {
        $this->badge = OrderResource::getNavigationBadge();
    }

    public function check(): void
    {
        // A poll can outlive the session (logout in another tab, expiry), and
        // a role can lose order access mid-session; either way stop reporting.
        if (! auth('web')->check() || ! OrderResource::canViewAny()) {
            return;
        }

        $badge = OrderResource::getNavigationBadge();

        if ($badge !== $this->badge) {
            $this->badge = $badge;
            $this->dispatch('refresh-sidebar');
        }
    }

    public function render(): View
    {
        // wire:poll is throttled by Livewire while the tab is in the background.
        return view('livewire.admin.pending-orders-poller');
    }
}
