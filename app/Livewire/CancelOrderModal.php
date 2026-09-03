<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CancelOrderModal extends Component
{
    public Order $order;
    public string $reason = '';
    public bool $isOpen = false;

    protected array $rules = [
        'reason' => 'required|string|in:change_of_mind,incorrect_items,found_better_price,other',
    ];

    public function cancelOrder()
    {
        $this->validate();

        if ($this->order->status !== 'pending') {
            session()->flash('error', 'Orders can only be cancelled while pending.');
            return;
        }

        DB::transaction(function () {
            $this->order->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $this->reason,
                'cancelled_at'        => now(),
            ]);

            // Restock inventory back to products/variants
            foreach ($this->order->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::where('id', $item->product_variant_id)->increment('stock_quantity', $item->quantity);
                } elseif ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
                }
            }
        });

        session()->flash('message', 'Your order has been cancelled successfully.');
        return redirect()->route('customer.orders.show', $this->order->id);
    }

    public function render()
    {
        return view('livewire.cancel-order-modal');
    }
}