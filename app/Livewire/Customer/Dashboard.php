<?php

namespace App\Livewire\Customer;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $customer = auth('customer')->user();

        $recentOrders = $customer->orders()
            ->with(['items.product.primaryImage', 'items.variant.images'])
            ->latest()
            ->limit(5)
            ->get();

        $stats = [
            'total_orders'      => $customer->orders()->count(),
            'pending_orders'    => $customer->orders()->where('status', 'pending')->count(),
            'processing_orders' => $customer->orders()->where('status', 'processing')->count(),
            'ready_orders'      => $customer->orders()->whereIn('status', ['ready_for_pickup', 'ready'])->count(),
            'completed_orders'  => $customer->orders()->where('status', 'completed')->count(),
            'cancelled_orders'  => $customer->orders()->where('status', 'cancelled')->count(),
            'total_spent'       => $customer->orders()->where('payment_status', 'paid')->sum('total'),
        ];

        $readyForPickupOrder = $customer->orders()
            ->whereIn('status', ['ready_for_pickup', 'ready'])
            ->latest()
            ->first();

        return view('livewire.customer.dashboard', [
            'recentOrders'        => $recentOrders,
            'stats'               => $stats,
            'readyForPickupOrder' => $readyForPickupOrder,
        ])
        ->layout('components.layouts.front-end-layout');
    }
}