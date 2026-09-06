<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Mail\OrderCancelledNoShowMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function makeReadyOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 500,
            'total' => 500,
            'pickup_date' => '2026-09-06',
            'pickup_slot' => '8:00 AM - 5:00 PM',
            'pickup_location' => 'UBAP_office',
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'ready_for_pickup',
        ], $attributes));
    }

    public function test_no_show_action_is_only_visible_after_the_pickup_period(): void
    {
        Carbon::setTestNow('2026-09-06 4:59 PM');
        $order = $this->makeReadyOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('cancel_no_show');

        Carbon::setTestNow('2026-09-06 5:00 PM');

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('cancel_no_show');
    }

    public function test_no_show_action_is_unavailable_without_a_ready_order_and_complete_schedule(): void
    {
        Carbon::setTestNow('2026-09-07 9:00 AM');
        $processingOrder = $this->makeReadyOrder(['status' => 'processing']);
        $unscheduledOrder = $this->makeReadyOrder([
            'pickup_date' => null,
            'pickup_slot' => null,
        ]);

        Livewire::test(EditOrder::class, ['record' => $processingOrder->getRouteKey()])
            ->assertActionHidden('cancel_no_show');

        Livewire::test(EditOrder::class, ['record' => $unscheduledOrder->getRouteKey()])
            ->assertActionHidden('cancel_no_show');
    }

    public function test_admin_no_show_cancellation_updates_order_releases_stock_and_emails_customer(): void
    {
        Carbon::setTestNow('2026-09-07 9:00 AM');
        $product = Product::factory()->create([
            'has_variants' => false,
            'stock_quantity' => 7,
        ]);
        $order = $this->makeReadyOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 250,
            'quantity' => 2,
            'subtotal' => 500,
        ]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('cancel_no_show')
            ->assertHasNoActionErrors();

        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('customer_no_show', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'cancelled',
            'user_id' => auth()->id(),
        ]);

        Mail::assertSent(
            OrderCancelledNoShowMail::class,
            fn (OrderCancelledNoShowMail $mail): bool => $mail->order->is($order),
        );
    }

    public function test_cancellation_email_contains_the_order_and_pickup_details(): void
    {
        $order = $this->makeReadyOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Green Cobra Shirt',
            'product_sku' => 'SHIRT-1',
            'variant_name' => 'Large',
            'price' => 250,
            'quantity' => 2,
            'subtotal' => 500,
        ]);
        $order->update(['payment_status' => 'failed']);

        $mail = new OrderCancelledNoShowMail($order->fresh());
        $html = $mail->render();

        $this->assertSame(
            'Your GreenCobraCart Order #'.$order->order_number.' Has Been Cancelled',
            $mail->envelope()->subject,
        );
        $this->assertStringContainsString('Green Cobra Shirt', $html);
        $this->assertStringContainsString('Large', $html);
        $this->assertStringContainsString('Sep 06, 2026', $html);
        $this->assertStringContainsString('8:00 AM - 5:00 PM', $html);
        $this->assertStringContainsString('UBAP Office', $html);
        $this->assertStringContainsString('Failed', $html);
        $this->assertStringContainsString('not collected during the scheduled pickup period', $html);
    }
}
