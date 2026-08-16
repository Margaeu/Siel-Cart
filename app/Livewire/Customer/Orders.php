<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;
    public $statusFilter = '';

    public function updatingStatusFilter(){
        $this->resetPage();
    }


    public function render()
    {
        $query = auth('customer')->user()->orders()
        ->with(['items.product'])
        ->latest();

        if ($this->statusFilter) {
            $query->where('status',$this->statusFilter);
        }

        $orders = $query->paginate(10);
        return view('livewire.orders',[
            'orders' => $orders
        ])
        ->layout('components.layouts.front-end-layout');
    }
}
