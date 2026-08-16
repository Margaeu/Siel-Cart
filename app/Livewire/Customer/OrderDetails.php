<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Component;

class OrderDetails extends Component
{

    public Order $order;

    public function mount($id){
        $this->order = Order::where('id',$id)
        ->where('customer_id',auth('customer')->id())
        ->with(['items.product.primaryImage','statusHistories'])
        ->firstOrFail();
    }
    public function render()
    {
        return view('livewire.customer.order-details')
        ->layout('components.layouts.front-end-layout', ['title' => 'Order Details']);
    }
}
