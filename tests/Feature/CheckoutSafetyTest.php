<?php

namespace Tests\Feature;

use App\Livewire\CheckoutPage;
use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutSafetyTest extends TestCase
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

    /** Run a competing change exactly between submission and its transaction reads. */
    private function atTransactionStart(callable $change): void
    {
        $pending = true;
        Event::listen(TransactionBeginning::class, function () use (&$pending, $change) {
            if ($pending) {
                $pending = false;
                $change();
            }
        });
    }

    public function test_order_prices_are_read_inside_the_transaction(): void
    {
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 44.99, 'stock_quantity' => 5]);
        app(CartService::class)->addItem($product->id, $variant->id, 2);
        $page = Livewire::test(CheckoutPage::class);
        $this->atTransactionStart(fn () => $variant->update(['price' => 49.99]));

        $page->call('placeOrder');

        $order = Order::sole();
        $this->assertSame('99.98', $order->total);
        $this->assertSame('49.99', $order->items()->sole()->price);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public static function unavailableChanges(): array
    {
        return [
            'product deactivated' => ['product_inactive'],
            'product deleted' => ['product_deleted'],
            'variant deactivated' => ['variant_inactive'],
            'variant deleted' => ['variant_deleted'],
            'product type changed' => ['product_type'],
            'variant reassigned' => ['variant_owner'],
        ];
    }

    #[DataProvider('unavailableChanges')]
    public function test_availability_is_revalidated_inside_the_transaction(string $change): void
    {
        $product = $this->product(['has_variants' => true]);
        $other = $this->product(['has_variants' => true]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        app(CartService::class)->addItem($product->id, $variant->id);
        $page = Livewire::test(CheckoutPage::class);
        $this->atTransactionStart(fn () => match ($change) {
            'product_inactive' => $product->update(['is_active' => false]),
            'product_deleted' => $product->delete(),
            'variant_inactive' => $variant->update(['is_active' => false]),
            'variant_deleted' => $variant->delete(),
            'product_type' => $product->update(['has_variants' => false]),
            'variant_owner' => $variant->update(['product_id' => $other->id]),
        });

        $page->call('placeOrder')->assertRedirect(route('cart.index'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        Mail::assertNothingOutgoing();
    }

    public function test_another_submission_consuming_the_cart_cannot_create_a_duplicate_order(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product->id, quantity: 2);
        $page = Livewire::test(CheckoutPage::class);
        $otherPage = new CheckoutPage;
        $this->atTransactionStart(fn () => $otherPage->placeOrder(app(CartService::class)));

        $page->call('placeOrder')->assertRedirect(route('cart.index'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        Mail::assertQueued(OrderConfirmation::class, 1);
    }

    public function test_client_supplied_prices_quantities_and_product_ids_are_ignored(): void
    {
        $product = $this->product();
        $other = $this->product();
        app(CartService::class)->addItem($product->id, quantity: 2);

        Livewire::test(CheckoutPage::class)
            ->set('cart.0.price', 0.01)->set('cart.0.quantity', 100)
            ->set('cart.0.product_id', $other->id)->call('placeOrder');

        $order = Order::sole();
        $line = $order->items()->sole();
        $this->assertSame('600.00', $order->total);
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame(2, $line->quantity);
        $this->assertSame(10, $other->fresh()->stock_quantity);
    }

    public function test_stock_failure_rolls_back_every_line_and_leaves_cart_intact(): void
    {
        $first = $this->product();
        $second = $this->product();
        $service = app(CartService::class);
        $service->addItem($first->id, quantity: 2);
        $service->addItem($second->id, quantity: 2);
        $page = Livewire::test(CheckoutPage::class);
        $second->update(['stock_quantity' => 1]);

        $page->call('placeOrder')->assertRedirect(route('cart.index'));

        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('cart_items', 2);
        Mail::assertNothingOutgoing();
    }

    public function test_duplicate_cart_lines_cannot_exceed_combined_stock(): void
    {
        $product = $this->product(['stock_quantity' => 3]);
        $service = app(CartService::class);
        $service->addItem($product->id, quantity: 2);
        $service->getCart()->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        Livewire::test(CheckoutPage::class)->call('placeOrder')->assertRedirect(route('cart.index'));

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_zero_quantity_cannot_create_an_order(): void
    {
        $product = $this->product();
        $service = app(CartService::class);
        $service->addItem($product->id);
        $page = Livewire::test(CheckoutPage::class);
        $service->getCart()->items()->update(['quantity' => 0]);

        $page->call('placeOrder')->assertRedirect(route('cart.index'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_order_failure_restores_stock_and_does_not_send_email(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product->id, quantity: 2);
        $page = Livewire::test(CheckoutPage::class);
        Order::creating(fn () => throw new \RuntimeException('Simulated order write failure'));

        $page->call('placeOrder')->assertSet('placingOrder', false)
            ->assertSee('We could not place your order. Please try again.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        Mail::assertNothingOutgoing();
    }

    public function test_email_failure_after_transaction_does_not_undo_order(): void
    {
        $product = $this->product();
        app(CartService::class)->addItem($product->id, quantity: 2);
        $page = Livewire::test(CheckoutPage::class);
        // RefreshDatabase owns an outer test transaction; checkout must have
        // finished its own transaction before handing anything to the mailer.
        $baselineLevel = DB::transactionLevel();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnUsing(function () use ($baselineLevel) {
            $this->assertSame($baselineLevel, DB::transactionLevel());
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('cart_items', 0);
            throw new \RuntimeException('Simulated email dispatch failure');
        });

        $page->call('placeOrder');

        $page->assertRedirect(route('customer.orders.show', Order::sole()->id));
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertStringContainsString('Your order was placed, but we could not send', session('order_success_message'));
    }
}
