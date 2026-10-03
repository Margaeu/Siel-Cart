<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * Cancelling returns an order's units to stock exactly once, however many
 * times or ways the cancellation is repeated (Order::restoreStock() and its
 * stock_restored_at marker). Units are taken out at checkout, so a second
 * credit would invent stock that was never there.
 */
class OrderRestockTest extends TestCase
{
    use BuildsResolvableOrders;
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        return $this->completedOrder([
            'status' => 'pending',
            'payment_status' => 'pending',
            'completed_at' => null,
        ]);
    }

    public function test_cancelling_returns_each_line_to_stock(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        [, $medium, $large] = $this->shirtWithSizes(mediumStock: 2, largeStock: 0);

        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 3);
        $this->line($order, $mug, quantity: 1);
        $this->line($order, $medium->product, $medium, quantity: 2);
        $this->line($order, $large->product, $large, quantity: 1);

        $order->updateStatus('cancelled');

        $this->assertSame(9, $mug->fresh()->stock_quantity, 'duplicate lines for one product are summed');
        $this->assertSame(4, $medium->fresh()->stock_quantity);
        $this->assertSame(1, $large->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_calling_restore_again_credits_nothing(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);

        $order->updateStatus('cancelled');

        $this->assertFalse($order->fresh()->restoreStock());
        $this->assertFalse($order->restoreStock(), 'a stale in-memory copy is re-read, not trusted');
        $this->assertSame(7, $mug->fresh()->stock_quantity);
    }

    public function test_repeating_the_cancellation_credits_nothing(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);

        $order->updateStatus('cancelled');
        // A second submit of the same cancel (double click, retried request).
        $order->fresh()->updateStatus('cancelled');

        $this->assertSame(7, $mug->fresh()->stock_quantity);
        $this->assertSame(2, $order->statusHistories()->where('status', 'cancelled')->count());
    }

    public function test_cancelling_again_after_a_status_change_credits_nothing(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);

        $order->updateStatus('cancelled');
        $order->fresh()->updateStatus('pending');
        $order->fresh()->updateStatus('cancelled');

        $this->assertSame(7, $mug->fresh()->stock_quantity);
    }

    public function test_a_soft_deleted_product_still_gets_its_units_back(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);
        $mug->delete();

        $order->updateStatus('cancelled');

        $this->assertSame(7, $mug->fresh()->stock_quantity);
    }

    public function test_deleting_an_order_does_not_restock_it(): void
    {
        // What the admin delete modals promise: deleting is not cancelling.
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);

        $order->delete();

        $this->assertSame(5, $mug->fresh()->stock_quantity);
        $this->assertNull(Order::withTrashed()->find($order->id)->stock_restored_at);
    }

    public function test_other_status_changes_do_not_restock(): void
    {
        $mug = $this->product(['stock_quantity' => 5]);
        $order = $this->pendingOrder();
        $this->line($order, $mug, quantity: 2);

        $order->updateStatus('processing');

        $this->assertSame(5, $mug->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }
}
