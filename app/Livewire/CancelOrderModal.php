<?php

namespace App\Livewire;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CancelOrderModal extends Component
{
    public Order $order;
    public string $reason = '';
    public bool $isOpen = false;

    protected array $rules = [
        'reason' => 'required|string|in:change_of_mind,incorrect_items',
    ];

    public function cancelOrder()
    {
        $this->validate();

        if ($this->order->status !== 'pending') {
            session()->flash('error', 'Orders can only be cancelled while pending.');
            return;
        }

        $cancelled = DB::transaction(function (): bool {
            // Re-read under a lock. The check above is only as good as the
            // moment it ran, and two taps on Cancel must not both get past it.
            // Restocking is guarded separately, but the cancellation reason
            // and timestamp would otherwise be overwritten by the loser.
            $order = Order::whereKey($this->order->id)
                ->where('customer_id', auth('customer')->id())
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== 'pending') {
                return false;
            }

            // Stock goes back through Order's status hook, so this path and
            // the admin's status dropdown credit it exactly the same way.
            $reasonLabel = ucwords(str_replace('_', ' ', $this->reason));

            $order->updateStatus(
                'cancelled',
                "Order cancelled by customer. Reason: {$reasonLabel}.",
                null,
                [
                    'cancellation_reason' => $this->reason,
                    'cancelled_at'        => now(),
                    'payment_status'      => 'cancelled',
                ],
            );

            return true;
        });

        $this->order->refresh();

        if (!$cancelled) {
            session()->flash('error', 'Orders can only be cancelled while pending.');
            return;
        }

        session()->flash('message', 'Your order has been cancelled successfully.');
        return redirect()->route('customer.orders.show', $this->order->id);
    }

    public function render()
    {
        return view('livewire.cancel-order-modal');
    }
}
