<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class OrderTabBadgeRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    public function test_status_badges_are_recalculated_during_livewire_refreshes(): void
    {
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 250,
            'total' => 250,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $component = Livewire::test(ListOrders::class);
        $tabs = $component->instance()->getCachedTabs();

        $this->assertFalse($tabs['pending']->isBadgeDeferred());
        $this->assertSame('1', $tabs['pending']->getBadge());
        $this->assertSame('0', $tabs['processing']->getBadge());

        $order->update(['status' => 'processing']);
        $component->call('$refresh');

        $tabs = $component->instance()->getCachedTabs();

        $this->assertSame('0', $tabs['pending']->getBadge());
        $this->assertSame('1', $tabs['processing']->getBadge());
    }

    public function test_order_table_polls_for_new_customer_orders(): void
    {
        $component = Livewire::test(ListOrders::class);

        $this->assertSame('10s', $component->instance()->getTable()->getPollingInterval());
    }
}
