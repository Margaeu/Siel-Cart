<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.front-end-layout')]
#[Title('My Orders')]
class Orders extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
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