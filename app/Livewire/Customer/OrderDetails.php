<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use App\Models\Review;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.front-end-layout')]
#[Title('Order Details')]
class OrderDetails extends Component
{
    public Order $order;

    /**
     * Product IDs reviewed against *this* order specifically, so the "Write
     * a Review" prompt still shows for a product that was reviewed under a
     * different (e.g. earlier) order for the same product.
     *
     * Includes reviews an admin deleted (withTrashed), so the order line
     * shows "Reviewed" rather than a "Write a Review" link the product page
     * would refuse -- see Review.
     */
    public array $reviewedProductIds = [];

    public function mount(int $id): void
    {
        $this->order = Order::where('id', $id)
            ->where('customer_id', auth('customer')->id())
            ->with(['customer', 'items.product.primaryImage', 'items.variant.images', 'items.resolutions', 'statusHistories'])
            ->firstOrFail();

        $productIds = $this->order->items->pluck('product_id')->filter()->unique()->values();

        $this->reviewedProductIds = Review::withTrashed()
            ->where('customer_id', auth('customer')->id())
            ->where('order_id', $this->order->id)
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
