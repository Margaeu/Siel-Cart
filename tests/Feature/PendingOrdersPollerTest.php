<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Livewire\Admin\PendingOrdersPoller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PendingOrdersPollerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authorization is not what these tests are about.
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    private function order(string $status = 'pending'): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 350,
            'total' => 350,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => $status,
        ]);
    }

    public function test_badge_counts_only_pending_orders(): void
    {
        $this->assertNull(OrderResource::getNavigationBadge());

        $this->order();
        $this->order();
        $this->order('processing');

        $this->assertSame('2', OrderResource::getNavigationBadge());
    }

    public function test_poll_does_not_refresh_the_sidebar_when_nothing_changed(): void
    {
        $this->order();

        Livewire::test(PendingOrdersPoller::class)
            ->call('check')
            ->assertNotDispatched('refresh-sidebar');
    }

    public function test_poll_refreshes_the_sidebar_when_a_new_order_arrives(): void
    {
        $poller = Livewire::test(PendingOrdersPoller::class);

        $this->order();

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('badge', '1');
    }

    public function test_poll_refreshes_the_sidebar_when_the_last_pending_order_is_picked_up(): void
    {
        $order = $this->order();
        $poller = Livewire::test(PendingOrdersPoller::class);

        $order->update(['status' => 'processing']);

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('badge', null);
    }
}
