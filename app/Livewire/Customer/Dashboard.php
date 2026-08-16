<?php

namespace App\Livewire\Customer;

use Livewire\Component;

class Dashboard extends Component
{

    public function render()
    {
        $customer = auth('customer')->user();

        $recentOrders = $customer->orders()
        ->with(['items.product'])
        ->latest()
        ->limit(5)
        ->get();

        $stats = [
            'total_orders' => $customer->orders()->count(),
            'pending_orders' => $customer->orders()->where('status','pending')->count(),
            'total_spent' => $customer->orders()->where('payment_status','paid')->sum('total'),
        ];
        
        return view('livewire.customer.dashboard',[
            'recentOrders' => $recentOrders,
            'stats' => $stats
        ])
        ->layout('components.layouts.front-end-layout');
    }
}
