<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\RefundRequest;
use Livewire\Component;
use Livewire\WithFileUploads;

class RequestRefundPage extends Component
{
    use WithFileUploads;

    public Order $order;
    public string $reason = 'defective';
    public string $customer_description = '';
    public array $proof_images = [];

    protected array $rules = [
        'reason'               => 'required|in:defective,damaged,item_mismatch',
        'customer_description' => 'required|string|min:10|max:1000',
        'proof_images'         => 'required|array|min:1|max:5',
        'proof_images.*'       => 'image|max:5120', // 5MB max per image
    ];

    public function mount(Order $order)
    {
        $this->order = $order;

        // Verify eligibility: completed status and within the 3-day window
        if ($order->status !== 'completed' || !$order->completed_at || now()->gt($order->completed_at->addDays(3))) {
            abort(403, 'This order is no longer eligible for a return or refund.');
        }
    }

    public function submitRefund()
    {
        $this->validate();

        $storedImages = [];
        foreach ($this->proof_images as $image) {
            $storedImages[] = $image->store('refund-proofs', 'public');
        }

        RefundRequest::create([
            'order_id'             => $this->order->id,
            'customer_id'          => auth('customer')->id(),
            'reason'               => $this->reason,
            'customer_description' => $this->customer_description,
            'proof_images'         => $storedImages,
            'refund_amount'        => $this->order->total,
            'status'               => 'pending',
        ]);

        session()->flash('message', 'Refund request submitted successfully. Please wait for admin approval.');
        return redirect()->route('customer.orders.show', $this->order->id);
    }

    public function render()
    {
        return view('livewire.request-refund-page');
    }
}