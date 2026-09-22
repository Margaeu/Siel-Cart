<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('components.layouts.front-end-layout')]
#[Title('My Orders')]
class Orders extends Component
{
    use WithPagination;

    // Bound to ?status= so the order-stage shortcuts on the account dashboard
    // can link straight to a filtered list, and so the selected tab survives
    // a refresh or a back-navigation from an order's detail page.
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    private const FILTERABLE_STATUSES = ['pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled'];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // The value now arrives from the query string, so anything hand-typed
        // falls back to "All" instead of rendering an empty list for a status
        // that doesn't exist.
        if (! in_array($this->statusFilter, self::FILTERABLE_STATUSES, true)) {
            $this->statusFilter = '';
        }

        $orders = Order::where('customer_id', auth('customer')->id())
            ->when($this->statusFilter, fn ($query) => $query->ofStatus($this->statusFilter))
            // The lines and the images display_image_url falls back to. The
            // items were not loaded at all before, so ten orders' worth of
            // rows were fetched one order at a time, then one image lookup
            // per line on top of that.
            ->with(['items.product.primaryImage', 'items.variant.images', 'items.resolutions'])
            ->latest()
            ->paginate(10);

        return view('livewire.orders', [
            'orders' => $orders,
        ])->layout('components.layouts.front-end-layout', ['title' => 'My Orders - '.config('app.name')]);
    }
}