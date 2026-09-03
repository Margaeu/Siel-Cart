<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.front-end-layout')]
#[Title('Order Details')]
class OrderDetails extends Component
{
    public Order $order;

    public function mount(int $id): void
    {
        $this->order = Order::where('id', $id)
            ->where('customer_id', auth('customer')->id())
            ->with(['customer', 'items.product.primaryImage', 'statusHistories'])
            ->firstOrFail();
    }

    public function requestReturn(): void
    {
        // 1. Security Check: Ensure the order belongs to the authenticated customer
        if ((int)$this->order->customer_id !== (int)auth('customer')->id()) {
            abort(403);
        }

        // 2. Strict Check: Allow return ONLY if order status is 'completed'
        if (strtolower($this->order->status) !== 'completed') {
            session()->flash('order_error', 'A return or refund request has already been submitted for this order.');
            return;
        }

        // 3. Update Order Status
        $this->order->update([
            'status' => 'return_requested',
        ]);

        // 4. Record Action in Status History
        if (method_exists($this->order, 'statusHistories')) {
            $this->order->statusHistories()->create([
                'user_id' => null,
                'status'  => 'return_requested',
                'notes'   => 'Customer submitted a return/refund request.',
            ]);
        }

        session()->flash('order_success_title', 'Request Submitted');
        session()->flash('order_success_message', 'Your return/refund request has been submitted and is pending review.');

        $this->order->refresh();
    }

    public function render()
    {
        return view('livewire.customer.order-details');
    }
}