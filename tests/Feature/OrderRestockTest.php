<?php

namespace Tests\Feature;

use App\Livewire\CancelOrderModal;
use App\Livewire\CheckoutPage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checkout takes units out of stock. These cover the other half: every way
 * an order can stop being a sale has to put them back, exactly once.
 */
class OrderRestockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs(Customer::factory()->create(), 'customer');
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true, 'has_variants' => false,
            'price' => 300, 'stock_quantity' => 10,
        ], $attributes));
    }

    /** Place a real order so the stock it consumed is the stock under test. */
    private function order(int $productId, ?int $variantId = null, int $quantity = 1): Order
    {
        app(CartService::class)->addItem($productId, $variantId, $quantity);
        Livewire::test(CheckoutPage::class)->call('placeOrder');

        return Order::latest('id')->firstOrFail();
    }

    public static function restockingStatuses(): array
    {
        return [
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('restockingStatuses')]
    public function test_admin_status_change_restocks_a_simple_product(string $status): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 3);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        // Exactly what the Filament status dropdown does.
        $order->update(['status' => $status]);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_admin_status_change_restocks_the_variant_that_was_sold(): void
    {
        $product = $this->product(['has_variants' => true]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        $order = $this->order($product->id, $variant->id, 2);
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(5, $variant->fresh()->stock_quantity);
        // The parent's own stock is not where a variant's units live.
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    /**
     * Returns are settled by UBAP outside the shop now, and whatever comes back
     * is defective, damaged, or was handed over in error -- none of it is fit
     * to sell again. The two statuses left over from the old self-service
     * workflow must not put it back on the shelf.
     */
    public function test_legacy_return_statuses_do_not_restock(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 3);

        $order->update(['status' => 'return_requested']);
        $order->update(['status' => 'return_completed']);

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_stock_is_credited_once_however_often_the_status_is_edited(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 3);

        $order->update(['status' => 'cancelled']);
        $order->update(['status' => 'cancelled', 'admin_notes' => 'touched again']);
        $order->update(['status' => 'return_completed']);
        $order->fresh()->restoreStock();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_customer_cancellation_restocks_and_records_the_reason(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 4);

        Livewire::test(CancelOrderModal::class, ['order' => $order])
            ->set('reason', 'change_of_mind')
            ->call('cancelOrder');

        $order->refresh();
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
        // These two were being dropped by mass assignment before.
        $this->assertSame('change_of_mind', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_a_second_cancellation_cannot_credit_the_stock_twice(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 4);

        foreach (range(1, 2) as $ignored) {
            Livewire::test(CancelOrderModal::class, ['order' => $order->fresh()])
                ->set('reason', 'change_of_mind')
                ->call('cancelOrder');
        }

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_two_lines_for_the_same_item_are_both_credited(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 3);

        // Older orders can hold a duplicate line for the same item.
        $line = $order->items()->sole();
        OrderItem::create(array_merge(
            $line->only(['order_id', 'product_id', 'product_name', 'price']),
            ['quantity' => 2, 'subtotal' => 600],
        ));
        $product->decrement('stock_quantity', 2);
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_force_deleting_a_product_leaves_the_order_line_intact(): void
    {
        $product = $this->product(['name' => 'CLSU Lanyard', 'sku' => 'LAN-1']);
        $order = $this->order($product->id, quantity: 2);

        $product->forceDelete();

        // The line survives, so the order still adds up to what was charged.
        $line = $order->items()->sole();
        $this->assertNull($line->product_id);
        $this->assertSame('CLSU Lanyard', $line->product_name);
        $this->assertSame('LAN-1', $line->product_sku);
        $this->assertSame('600.00', $order->fresh()->total);
        $this->assertSame('600.00', $line->subtotal);
    }

    public function test_cancelling_an_order_whose_product_is_gone_still_succeeds(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 2);
        $product->forceDelete();

        $order->update(['status' => 'cancelled']);

        // Nothing left to credit, but the cancellation must still go through.
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_a_soft_deleted_product_is_still_credited(): void
    {
        $product = $this->product();
        $order = $this->order($product->id, quantity: 3);
        $product->delete();

        $order->update(['status' => 'cancelled']);

        // The units physically came back and the product can be restored.
        $this->assertSame(10, Product::withTrashed()->find($product->id)->stock_quantity);
    }
}
