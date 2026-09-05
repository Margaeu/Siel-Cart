<?php

namespace Tests\Feature;

use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\ProductCard;
use App\Livewire\ProductDetails;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Customer::factory()->create(), 'customer');
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'has_variants' => false,
            'is_active' => true,
            'price' => 300,
            'stock_quantity' => 20,
        ], $attributes));
    }

    public function test_cart_uses_the_selected_variant_price_and_keeps_selection_after_reordering(): void
    {
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $cheap = ProductVariant::factory()->for($product)->create([
            'price' => 44.99, 'stock_quantity' => 5, 'sort_order' => 0,
        ]);
        $selected = ProductVariant::factory()->for($product)->create([
            'name' => 'Large Green', 'price' => 89.99, 'stock_quantity' => 5, 'sort_order' => 1,
        ]);
        $simple = $this->product();
        $service = app(CartService::class);
        $this->assertTrue($service->addItem($product->id, $selected->id, 2)['success']);
        $this->assertTrue($service->addItem($simple->id)['success']);

        Livewire::test(CartPage::class)
            ->assertSee('Large Green')->assertSee('₱89.99')->assertSee('₱179.98')
            ->assertSee('₱300.00')->assertSee('₱479.98')
            ->assertDontSee('₱750.00')->assertDontSee('From ₱')->assertDontSee('₱44.99');
        $this->assertSame(479.98, $service->getSubtotal());
        $this->assertSame(479.98, $service->getCart()->total);

        $selected->update(['price' => 99.99, 'sort_order' => 0]);
        $cheap->update(['sort_order' => 1]);
        Livewire::test(CartPage::class)
            ->assertSee('Large Green')->assertSee('₱99.99')->assertSee('₱499.98');
        $this->assertSame($selected->id, $service->getCart()->items()->where('product_id', $product->id)->first()->product_variant_id);
        $this->assertSame(499.98, $service->getSubtotal());
    }

    public function test_product_details_confirms_the_exact_variant_added_to_the_cart(): void
    {
        $product = $this->product([
            'name' => 'CLSU Shirt',
            'has_variants' => true,
            'price' => null,
        ]);
        $variant = ProductVariant::factory()->for($product)->create([
            'name' => 'Yellow',
            'sku' => 'SHIRT-YELLOW',
            'price' => 250,
            'stock_quantity' => 5,
        ]);

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSet('selectedVariant', $variant->id)
            ->call('addToCart')
            ->assertDispatched(
                'cart-added',
                message: 'CLSU Shirt — Yellow has been added to your cart.',
            );

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('@cart-added.window', false);
    }

    public function test_deleted_variant_never_falls_back_to_parent_price_and_blocks_checkout(): void
    {
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 44.99, 'stock_quantity' => 5]);
        $service = app(CartService::class);
        $service->addItem($product->id, $variant->id);
        $variant->delete();

        $item = $service->getCart()->items()->first();
        $this->assertNull($item->product_variant_id);
        $this->assertNull($item->price);
        $this->assertSame(0.0, $service->getSubtotal());
        $this->assertTrue($service->hasUnavailableItems());
        Livewire::test(CartPage::class)->assertSee('Price unavailable')
            ->assertDontSee('₱750.00')->assertDontSee('Proceed to Checkout');
        Livewire::test(CheckoutPage::class)->assertRedirect(route('cart.index'));
        Livewire::test(CartPage::class)->assertSee('Some items in your cart are no longer available.');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_deactivated_variant_stops_pricing_the_cart_row_and_blocks_checkout(): void
    {
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $variant = ProductVariant::factory()->for($product)->create([
            'name' => 'Large Green', 'price' => 44.99, 'stock_quantity' => 5,
        ]);
        // A second, still-sellable row so the totals show the deactivated
        // one dropping out rather than the cart simply emptying.
        $simple = $this->product();
        $service = app(CartService::class);
        $service->addItem($product->id, $variant->id, 2);
        $service->addItem($simple->id);

        $variant->update(['is_active' => false]);

        // The product module already treats a deactivated variant as gone,
        // and the cart has to reach the same answer.
        $this->assertNull($product->fresh()->display_price);
        $item = $service->getCart()->items()->where('product_id', $product->id)->first();
        $this->assertNull($item->price);
        $this->assertSame(0.0, $item->subtotal);
        $this->assertSame(300.0, $service->getSubtotal());
        $this->assertSame(300.0, $service->getCart()->total);
        $this->assertTrue($service->hasUnavailableItems());
        $this->assertFalse($service->updateQuantity($item->id, 1)['success']);

        Livewire::test(CartPage::class)
            ->assertSee('Price unavailable')
            ->assertDontSee('₱44.99')->assertDontSee('₱89.98')->assertDontSee('₱750.00')
            ->assertDontSee('Proceed to Checkout');
        Livewire::test(CheckoutPage::class)->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_simple_product_without_a_price_is_refused_instead_of_added(): void
    {
        // products.price is nullable so variant-priced products can leave it
        // empty. A product switched back off variants can therefore be sold
        // on its own with no price at all.
        $product = $this->product(['price' => null]);
        $service = app(CartService::class);

        $result = $service->addItem($product->id);

        $this->assertFalse($result['success']);
        $this->assertSame('This product is not available for purchase right now.', $result['message']);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertFalse($service->hasUnavailableItems());

        // The card surfaces the refusal instead of silently adding nothing.
        Livewire::test(ProductCard::class, ['product' => $product])
            ->call('addToCart')
            ->assertDispatched('cart-error')
            ->assertNotDispatched('cart-added');

        // Priced again, it goes in and totals normally.
        $product->update(['price' => 120]);
        $this->assertTrue($service->addItem($product->id)['success']);
        $this->assertSame(120.0, $service->getSubtotal());
    }

    public function test_unsellable_rows_are_left_out_of_the_order_summary(): void
    {
        $dead = $this->product(['price' => 300]);
        $live = $this->product(['price' => 100]);
        $service = app(CartService::class);
        $service->addItem($dead->id, null, 2);
        $service->addItem($live->id);
        $this->assertSame(700.0, $service->getSubtotal());

        $dead->update(['is_active' => false]);

        // The row keeps its own price -- the product still has one, and the
        // customer should see what it was -- but it stops counting.
        $item = $service->getCart()->items()->where('product_id', $dead->id)->first();
        $this->assertSame(300.0, $item->price);
        $this->assertSame(600.0, $item->subtotal);
        $this->assertSame(0.0, $item->payable_subtotal);
        $this->assertSame(100.0, $service->getSubtotal());
        $this->assertSame(100.0, $service->getCart()->total);
        // The header badge still counts everything, so the broken row is not
        // quietly hidden from the customer who has to go deal with it.
        $this->assertSame(3, $service->getCart()->total_quantity);

        Livewire::test(CartPage::class)
            ->assertSee('Subtotal (1 items)')
            ->assertSee('1 unavailable item not included.')
            ->assertSee('₱100.00')
            ->assertDontSee('₱700.00')
            ->assertDontSee('Proceed to Checkout');
    }

    public function test_an_over_stock_row_still_counts_towards_the_summary(): void
    {
        // Over-stock is the one problem the customer fixes by lowering the
        // quantity rather than removing the item, so it keeps its price.
        $product = $this->product(['price' => 100, 'stock_quantity' => 20]);
        $service = app(CartService::class);
        $service->addItem($product->id, null, 5);

        $product->update(['stock_quantity' => 2]);

        $item = $service->getCart()->items()->first()->fresh('product');
        $this->assertTrue($item->is_over_stock);
        $this->assertSame(500.0, $item->payable_subtotal);
        $this->assertSame(500.0, $service->getSubtotal());

        Livewire::test(CartPage::class)
            ->assertSee('Subtotal (5 items)')
            ->assertSee('₱500.00')
            ->assertDontSee('not included.')
            ->assertSee('Only 2 left in stock.');
    }

    public function test_a_selection_the_shop_dropped_offers_no_stock(): void
    {
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $variant = ProductVariant::factory()->for($product)->create([
            'price' => 44.99, 'stock_quantity' => 5,
        ]);
        $elsewhere = $this->product(['has_variants' => true, 'price' => 750]);
        $service = app(CartService::class);
        $service->addItem($product->id, $variant->id, 2);

        $item = $service->getCart()->items()->first();
        $this->assertSame(5, $item->available_stock);

        // Deactivated: the units are still on the row, but none are for sale,
        // so the cart must not size a quantity control against them.
        $variant->update(['is_active' => false]);
        $this->assertSame(0, $item->fresh(['product', 'variant'])->available_stock);

        // Reassigned to another product: the same answer.
        $variant->update(['is_active' => true, 'product_id' => $elsewhere->id]);
        $this->assertSame(0, $item->fresh(['product', 'variant'])->available_stock);
    }

    public static function productTypes(): array
    {
        return ['simple' => [false], 'variable' => [true]];
    }

    #[DataProvider('productTypes')]
    public function test_changing_product_type_invalidates_old_cart_selection(bool $hasVariants): void
    {
        $product = $this->product(['has_variants' => $hasVariants]);
        $variant = $hasVariants
            ? ProductVariant::factory()->for($product)->create(['stock_quantity' => 5])
            : null;
        $service = app(CartService::class);
        $service->addItem($product->id, $variant?->id);
        $product->update(['has_variants' => ! $hasVariants]);

        $item = $service->getCart()->items()->first();
        $this->assertNull($item->price);
        $this->assertTrue($service->hasUnavailableItems());
        $this->assertFalse($service->updateQuantity($item->id, 2)['success']);
        Livewire::test(CartPage::class)->assertSee('Price unavailable')->assertDontSee('Proceed to Checkout');
    }

    #[DataProvider('productTypes')]
    public function test_admin_stock_reduction_can_be_resolved_in_cart_without_removing_item(bool $hasVariants): void
    {
        $product = $this->product(['has_variants' => $hasVariants]);
        $variant = $hasVariants
            ? ProductVariant::factory()->for($product)->create(['stock_quantity' => 5])
            : null;
        $service = app(CartService::class);
        $service->addItem($product->id, $variant?->id, 4);
        $item = $service->getCart()->items()->first();
        $page = Livewire::test(CartPage::class);
        ($variant ?? $product)->update(['stock_quantity' => 2]);

        $page->call('updateQuantity', $item->id, 5)
            ->assertSee('Only 2 item(s) are available in stock.')
            ->assertSee('Lower quantities that exceed available stock')
            ->assertDontSee('Proceed to Checkout')
            ->call('updateQuantity', $item->id, 2)
            ->assertSet('quantityError', null)->assertSee('Proceed to Checkout');
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertFalse($service->hasUnavailableItems());
    }

    #[DataProvider('productTypes')]
    public function test_low_stock_threshold_does_not_prevent_buying_available_stock(bool $hasVariants): void
    {
        $product = $this->product(['has_variants' => $hasVariants, 'stock_quantity' => 3]);
        $variant = $hasVariants
            ? ProductVariant::factory()->for($product)->create(['stock_quantity' => 3])
            : null;
        $service = app(CartService::class);

        foreach ([0, 10] as $threshold) {
            ($variant ?? $product)->update(['low_stock_threshold' => $threshold]);
            $this->assertTrue($service->addItem($product->id, $variant?->id)['success']);
            $this->assertFalse($service->hasUnavailableItems());
        }
        $this->assertTrue($service->addItem($product->id, $variant?->id)['success']);
        $this->assertFalse($service->addItem($product->id, $variant?->id)['success']);
    }

    public function test_checkout_uses_current_cart_price_and_orders_preserve_that_price(): void
    {
        Mail::fake();
        $product = $this->product(['has_variants' => true, 'price' => 750]);
        $variant = ProductVariant::factory()->for($product)->create(['price' => 44.99, 'stock_quantity' => 5]);
        $simple = $this->product();
        $service = app(CartService::class);
        $service->addItem($product->id, $variant->id, 2);
        $service->addItem($simple->id);
        $checkout = Livewire::test(CheckoutPage::class)->assertSee('₱389.98');

        // Admin edits after checkout opens must be read again at submission.
        $variant->update(['price' => 49.99]);
        $simple->update(['price' => 310]);
        $checkout->call('placeOrder');
        $order = Order::sole();
        $checkout->assertRedirect(route('customer.orders.show', $order->id));
        $this->assertSame('409.98', $order->total);
        $line = $order->items()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame('49.99', $line->price);
        $this->assertSame('99.98', $line->subtotal);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(20, $product->fresh()->stock_quantity);
        $this->assertSame(19, $simple->fresh()->stock_quantity);

        $variant->update(['price' => 60]);
        $simple->update(['price' => 400]);
        $this->get(route('customer.orders.show', $order->id))
            ->assertOk()->assertSee('₱49.99')->assertSee('₱409.98')->assertDontSee('₱750.00');
    }

    /**
     * products.sku is nullable, because a product sold by variant has none of
     * its own. The order snapshot has to accept that instead of failing the
     * insert, and a variant line still snapshots the variant's own SKU.
     */
    public function test_checkout_snapshots_a_missing_product_sku_without_failing(): void
    {
        Mail::fake();
        $variable = $this->product(['has_variants' => true, 'price' => null, 'sku' => null]);
        $variant = ProductVariant::factory()->for($variable)->create([
            'sku' => 'VAR-GREEN-L', 'price' => 120, 'stock_quantity' => 5,
        ]);
        $simple = $this->product(['sku' => null]);
        $service = app(CartService::class);
        $this->assertTrue($service->addItem($variable->id, $variant->id)['success']);
        $this->assertTrue($service->addItem($simple->id)['success']);

        $checkout = Livewire::test(CheckoutPage::class)->assertSee('SKU: VAR-GREEN-L');
        // The line without a SKU is omitted rather than labelled "SKU:" with
        // nothing after it, so only the variant line carries the label.
        $this->assertSame(1, substr_count($checkout->html(), 'SKU:'));
        $checkout->call('placeOrder');

        $order = Order::sole();
        $this->assertSame('420.00', $order->total);
        $this->assertSame('VAR-GREEN-L', $order->items()->where('product_variant_id', $variant->id)->sole()->product_sku);
        $this->assertNull($order->items()->where('product_id', $simple->id)->sole()->product_sku);
        $details = $this->get(route('customer.orders.show', $order->id))
            ->assertOk()->assertSee('SKU: VAR-GREEN-L');
        $this->assertSame(1, substr_count($details->getContent(), 'SKU:'));
    }
}
