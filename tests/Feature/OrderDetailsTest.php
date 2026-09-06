<?php

namespace Tests\Feature;

use App\Livewire\CheckoutPage;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the page the customer lands on straight after checkout.
 *
 * Products are built by hand rather than through ProductFactory, which
 * currently still has an unresolved merge conflict in it.
 */
class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $name, float $price, int $stock = 10): Product
    {
        return Product::create([
            'category_id'    => Category::factory()->create()->id,
            'name'           => $name,
            'slug'           => str($name)->slug() . '-' . uniqid(),
            'sku'            => 'SKU-' . strtoupper(uniqid()),
            'price'          => $price,
            'stock_quantity' => $stock,
            'is_active'      => true,
        ]);
    }

    /**
     * An order with one plain line and one line that carried a variation.
     */
    protected function makeOrder(Customer $customer): Order
    {
        $shirt = $this->makeProduct('CLSU Lanyard', 75.50);
        $mug   = $this->makeProduct('CLSU Tumbler', 320.00);

        $order = Order::create([
            'customer_id'         => $customer->id,
            'subtotal'            => 471.00,
            'total'               => 471.00,
            'pickup_location'     => CheckoutPage::PICKUP_LOCATION,
            'claimant_name'       => $customer->name,
            'payment_method'      => CheckoutPage::PAYMENT_METHOD,
            'payment_status'      => 'pending',
            'status'              => 'pending',
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $shirt->id,
            'product_name' => $shirt->name,
            'product_sku'  => $shirt->sku,
            'variant_name' => 'Green / Large',
            'price'        => 75.50,
            'quantity'     => 2,
            'subtotal'     => 151.00,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $mug->id,
            'product_name' => $mug->name,
            'product_sku'  => $mug->sku,
            'variant_name' => null,
            'price'        => 320.00,
            'quantity'     => 1,
            'subtotal'     => 320.00,
        ]);

        return $order->fresh();
    }

    public function test_it_shows_every_detail_of_the_order(): void
    {
        $customer = Customer::factory()->create();
        $order    = $this->makeOrder($customer);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order->id));

        $response->assertOk();

        // Order number and date placed.
        $response->assertSee($order->order_number);
        $response->assertSee($order->created_at->format('M d, Y h:i A'));

        // Customer name and email.
        $response->assertSee('Customer Information');
        $response->assertSee($customer->name);
        $response->assertSee($customer->email);

        // Products, variations, unit prices, quantities and line subtotals.
        $response->assertSee('CLSU Lanyard');
        $response->assertSee('CLSU Tumbler');
        $response->assertSee('Variation: Green / Large');
        $response->assertSee('Quantity: 2 × ₱75.50', false);
        $response->assertSee('Quantity: 1 × ₱320.00', false);
        $response->assertSee('₱151.00', false);

        // Merchandise subtotal and order total.
        $response->assertSee('Merchandise Subtotal');
        $response->assertSee('Order Total');
        $response->assertSee('₱471.00', false);

        // Payment method and payment status.
        $response->assertSee('Cash on Pickup');
        $response->assertSee('Pending');
    }

    public function test_an_order_with_no_claimant_yet_says_so_instead_of_showing_a_blank(): void
    {
        $customer = Customer::factory()->create();
        $order    = $this->makeOrder($customer);

        $order->update([
            'claimant_name'  => null,
            'claimant_phone' => null,
        ]);

        $this->assertNull($order->claimant_name);
        $this->assertNull($order->claimant_phone);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Claimed By')
            ->assertSee('To be designated');
    }

    public function test_a_cancelled_order_summary_shows_a_readable_cancellation_reason(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->makeOrder($customer);

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'failed',
            'cancellation_reason' => 'customer_no_show',
            'cancelled_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Cancellation Reason')
            ->assertSee('The order was not collected during the scheduled pickup period.');
    }

    public function test_the_success_message_is_shown_only_right_after_the_order_is_placed(): void
    {
        $customer = Customer::factory()->create();
        $order    = $this->makeOrder($customer);

        // Stand in for the checkout redirect. Flashing through a real
        // redirect is the point of the test: session()->withSession() would
        // write permanent session data, which never expires and so would
        // pass the first leg while telling us nothing about the second.
        Route::middleware('web')->get('/test-order-redirect/{id}', fn ($id) => redirect()
            ->route('customer.orders.show', $id)
            ->with([
                'order_success_title'   => 'Order placed successfully!',
                'order_success_message' => 'Thank you for your purchase! Please wait for an email confirmation to know when your order is being processed.',
            ]));

        $this->actingAs($customer, 'customer');

        // Landing on the page straight after checkout.
        $this->followingRedirects()
            ->get('/test-order-redirect/' . $order->id)
            ->assertOk()
            ->assertSee('Order placed successfully!')
            ->assertSee('Thank you for your purchase! Please wait for an email confirmation to know when your order is being processed.');

        // Refreshing, or coming back to the order later, must not repeat it.
        $this->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertDontSee('Order placed successfully!')
            ->assertDontSee('Thank you for your purchase!');
    }

    public function test_a_customer_cannot_open_another_customers_order(): void
    {
        $owner     = Customer::factory()->create();
        $intruder  = Customer::factory()->create();
        $order     = $this->makeOrder($owner);

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertNotFound();
    }

    public function test_a_guest_cannot_open_an_order(): void
    {
        $customer = Customer::factory()->create();
        $order    = $this->makeOrder($customer);

        $this->get(route('customer.orders.show', $order->id))
            ->assertRedirect();
    }

    /**
     * placeOrder() does not write a claimant, because who collects the order
     * is settled after checkout rather than during it. This covers the whole
     * hand-off, so the order has to be creatable without one.
     */
    public function test_placing_an_order_redirects_to_it_and_flashes_the_success_message(): void
    {
        $customer = Customer::factory()->create();
        $product  = $this->makeProduct('CLSU Cap', 250.00, 5);

        $this->actingAs($customer, 'customer');

        $cart = Cart::create(['customer_id' => $customer->id]);
        $cart->items()->create([
            'product_id'         => $product->id,
            'product_variant_id' => null,
            'quantity'           => 2,
        ]);

        $component = Livewire::actingAs($customer, 'customer')
            ->test(CheckoutPage::class)
            ->call('placeOrder');

        $order = Order::latest('id')->first();

        $this->assertNotNull($order, 'Placing the order should have created an order.');

        $component->assertRedirect(route('customer.orders.show', $order->id));

        $this->assertEquals(
            'Order placed successfully!',
            session('order_success_title'),
        );

        $this->assertEquals(
            'Thank you for your purchase! Please wait for an email confirmation to know when your order is being processed.',
            session('order_success_message'),
        );
    }
}
