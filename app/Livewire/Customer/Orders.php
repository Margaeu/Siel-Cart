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

    public function render()
    {
        $orders = Order::where('customer_id', auth('customer')->id())
            ->latest()
            ->paginate(10);

        return view('livewire.orders', [
            'orders' => $orders,
        ]);
    }
}