<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use App\Models\Review;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.front-end-layout')]
#[Title('Order Details')]
class OrderDetails extends Component
{
    public Order $order;

    /**
     * Product IDs (from this order) the customer has already reviewed, so
     * the "Review Required" prompt only shows for items still pending one.
     */
    public array $reviewedProductIds = [];

    public function mount(int $id): void
    {
        $this->order = Order::where('id', $id)
            ->where('customer_id', auth('customer')->id())
            ->with(['customer', 'items.product.primaryImage', 'items.variant.images', 'items.resolutions', 'statusHistories'])
            ->firstOrFail();

        $productIds = $this->order->items->pluck('product_id')->filter()->unique()->values();

        $this->reviewedProductIds = Review::where('customer_id', auth('customer')->id())
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
    }

    public function render()
    {
        return view('livewire.customer.order-details')
            ->layout('components.layouts.front-end-layout', ['title' => 'Order Details - '.config('app.name')]);
    }
}