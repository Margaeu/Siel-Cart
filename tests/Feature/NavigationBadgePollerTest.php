<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Livewire\Admin\NavigationBadgePoller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_sidebar_and_poller_share_one_count_query_per_queue(): void
    {
        $counts = 0;
        DB::listen(function ($query) use (&$counts): void {
            if (str_contains($query->sql, 'count(*)') && preg_match('/from "(orders|reviews|reports)"/', $query->sql)) {
                $counts++;
            }
        });

        OrderResource::getNavigationBadge();
        ReviewResource::getNavigationBadge();
        ReportResource::getNavigationBadge();
        Livewire::test(NavigationBadgePoller::class);

        $this->assertSame(3, $counts);
    }

    public function test_badges_are_fresh_after_moderation_deletion_and_restore(): void
    {
        $review = $this->review();
        $report = $this->report();
        $order = $this->order();

        $this->assertSame('2', ReviewResource::getNavigationBadge());
        $this->assertSame('1', ReportResource::getNavigationBadge());
        $this->assertSame('1', OrderResource::getNavigationBadge());

        $review->update(['is_approved' => true]);
        $report->update(['status' => 'resolved']);
        $order->delete();

        $this->assertSame('1', ReviewResource::getNavigationBadge());
        $this->assertNull(ReportResource::getNavigationBadge());
        $this->assertNull(OrderResource::getNavigationBadge());

        $order->restore();
        $this->assertSame('1', OrderResource::getNavigationBadge());
    }

    public function test_poll_reads_changes_that_bypass_model_events(): void
    {
        $order = $this->order();
        $poller = Livewire::test(NavigationBadgePoller::class);

        Order::whereKey($order->id)->update(['status' => 'processing']);

        $poller->call('check')
            ->assertDispatched('refresh-sidebar')
            ->assertSet('ordersBadge', null);
    }

    public function test_badge_counts_do_not_carry_over_to_a_new_request_scope(): void
    {
        $order = $this->order();
        $this->assertSame('1', OrderResource::getNavigationBadge());

        Order::whereKey($order->id)->update(['status' => 'processing']);
        $this->app->forgetScopedInstances();

        $this->assertNull(OrderResource::getNavigationBadge());
    }
}
