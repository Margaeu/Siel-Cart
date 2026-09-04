<?php

namespace Tests\Feature;

use App\Livewire\CheckoutPage;
use App\Livewire\Customer\Dashboard;
use App\Livewire\Customer\OrderDetails;
use App\Livewire\Customer\Orders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An order line has to keep displaying after the product behind it changes
 * or goes away. Every page that shows one must read the same snapshot.
 */
class OrderLineDisplayTest extends TestCase
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

    private function order(int $productId, ?int $variantId = null): Order
    {
        app(CartService::class)->addItem($productId, $variantId);
        Livewire::test(CheckoutPage::class)->call('placeOrder');

        return Order::latest('id')->firstOrFail();
    }

    public function test_the_snapshot_taken_at_checkout_is_preferred(): void
    {
        $product = $this->product();
        ProductImage::factory()->primary()->for($product)->create(['image_path' => 'as-bought.jpg']);
        $item = $this->order($product->id)->items()->sole();

        // The admin swaps the catalogue picture afterwards.
        $product->primaryImage->update(['image_path' => 'replaced.jpg']);

        $this->assertStringContainsString('as-bought.jpg', $item->fresh()->display_image_url);
    }

    public function test_a_soft_deleted_product_still_resolves_for_its_order_lines(): void
    {
        $product = $this->product();
        ProductImage::factory()->primary()->for($product)->create();
        $item = $this->order($product->id)->items()->sole();
        // Clear the snapshot so the live fallback is what is under test.
        $item->update(['product_image' => null]);

        $product->delete();

        $item = $item->fresh();
        $this->assertNotNull($item->product, 'the trashed product should still load');
        $this->assertNotNull($item->display_image_url);
    }

    public function test_a_force_deleted_product_leaves_the_line_displayable(): void
    {
        $product = $this->product(['name' => 'CLSU Tumbler']);
        $item = $this->order($product->id)->items()->sole();
        $item->update(['product_image' => null]);

        $product->forceDelete();

        $item = $item->fresh();
        $this->assertNull($item->product);
        $this->assertNull($item->display_image_url);
        // The name still comes from the snapshot, so the page has something
        // to render in place of the picture.
        $this->assertSame('CLSU Tumbler', $item->product_name);
    }

    public function test_a_variant_line_shows_its_own_picture_not_the_parents(): void
    {
        $product = $this->product(['has_variants' => true]);
        ProductImage::factory()->primary()->for($product)->create(['image_path' => 'parent.jpg']);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $variant->id, 'image_path' => 'variant.jpg',
        ]);

        $item = $this->order($product->id, $variant->id)->items()->sole();
        $item->update(['product_image' => null]);

        $this->assertStringContainsString('variant.jpg', $item->fresh()->display_image_url);
    }

    public static function orderPages(): array
    {
        return [
            'order list' => [Orders::class],
            'dashboard' => [Dashboard::class],
        ];
    }

    /**
     * Both of these used to read product->primaryImage directly, so they
     * showed nothing once the product was deleted and ignored the snapshot
     * and the variant image even while it was there.
     */
    #[DataProvider('orderPages')]
    public function test_order_pages_render_the_snapshot_for_a_deleted_product(string $page): void
    {
        $product = $this->product(['name' => 'CLSU Lanyard']);
        ProductImage::factory()->primary()->for($product)->create(['image_path' => 'lanyard.jpg']);
        $this->order($product->id);

        $product->delete();

        Livewire::test($page)
            ->assertOk()
            ->assertSee('CLSU Lanyard')
            // The picture, not just the name. Reading the live relation
            // instead of the snapshot rendered no <img> at all here.
            ->assertSee('lanyard.jpg');
    }

    #[DataProvider('orderPages')]
    public function test_order_pages_show_the_variant_picture_for_a_variant_line(string $page): void
    {
        $product = $this->product(['has_variants' => true]);
        ProductImage::factory()->primary()->for($product)->create(['image_path' => 'parent.jpg']);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $variant->id, 'image_path' => 'variant.jpg',
        ]);

        $this->order($product->id, $variant->id);

        Livewire::test($page)
            ->assertOk()
            ->assertSee('variant.jpg')
            ->assertDontSee('parent.jpg');
    }

    public function test_the_order_details_page_renders_a_line_with_no_product_left(): void
    {
        $product = $this->product(['name' => 'CLSU Lanyard']);
        $order = $this->order($product->id);
        $order->items()->update(['product_image' => null]);

        $product->forceDelete();

        Livewire::test(OrderDetails::class, ['id' => $order->id])
            ->assertOk()
            ->assertSee('CLSU Lanyard');
    }
}
