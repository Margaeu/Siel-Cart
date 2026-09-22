<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Livewire\Admin\NavigationBadgePoller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationBadgePollerTest extends TestCase
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

    private function review(bool $isApproved = false, ?Customer $customer = null): Review
    {
        return Review::create([
            'product_id' => Product::factory()->create()->id,
            'customer_id' => ($customer ?? Customer::factory()->create())->id,
            'rating' => 5,
            'is_approved' => $isApproved,
        ]);
    }

    private function report(string $status = 'pending'): Report
    {
        $reporter = Customer::factory()->create();
        $reported = Customer::factory()->create();

        return Report::create([
            'reporter_customer_id' => $reporter->id,
            'reported_customer_id' => $reported->id,
            'review_id' => $this->review(customer: $reported)->id,
            'reason' => 'inappropriate',
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

        Livewire::test(NavigationBadgePoller::class)
            ->call('check')
            ->assertNotDispatched('refresh-sidebar');
    }

    public function test_poll_refreshes_the_sidebar_when_a_new_order_arrives(): void
    {
        $poller = Livewire::test(NavigationBadgePoller::class);

        $this->order();

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('ordersBadge', '1');
    }

    public function test_poll_refreshes_the_sidebar_when_the_last_pending_order_is_picked_up(): void
    {
        $order = $this->order();
        $poller = Livewire::test(NavigationBadgePoller::class);

        $order->update(['status' => 'processing']);

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('ordersBadge', null);
    }

    public function test_poll_refreshes_the_sidebar_when_a_new_unapproved_review_arrives(): void
    {
        $poller = Livewire::test(NavigationBadgePoller::class);

        $this->review(isApproved: false);

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('reviewsBadge', '1');
    }

    public function test_poll_refreshes_the_sidebar_when_a_new_pending_report_arrives(): void
    {
        $poller = Livewire::test(NavigationBadgePoller::class);

        $this->report();

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('reportsBadge', '1');
    }
}
