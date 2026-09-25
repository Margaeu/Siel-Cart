<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\ForceDeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Product images are removed from R2 in a DB::afterCommit() callback, so
 * these tests use DatabaseMigrations rather than RefreshDatabase: with no
 * wrapping test transaction, saves and deletes really commit (or roll back)
 * and the callback runs exactly as it would in production.
 */
class ProductMediaCleanupTest extends TestCase
{
    use DatabaseMigrations;

    private \Closure $undoRepeaterFake;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        $this->undoRepeaterFake = Repeater::fake();
    }

    protected function tearDown(): void
    {
        ($this->undoRepeaterFake)();

        parent::tearDown();
    }

    private function actingAsAdmin(): void
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    private function storedImage(Product $product, string $path, ?ProductVariant $variant = null, array $attributes = []): ProductImage
    {
        Storage::disk('r2')->put($path, 'image-bytes');

        return ProductImage::factory()->for($product)->create([
            'image_path' => $path,
            'product_variant_id' => $variant?->id,
            ...$attributes,
        ]);
    }

    /**
     * A variant product with a shared image, two variants with an image
     * each, and a completed order line for one variant.
     *
     * @return array{product: Product, green: ProductVariant, white: ProductVariant, orderItem: OrderItem}
     */
    private function catalogueWithHistory(): array
    {
        $product = Product::factory()->create([
            'name' => 'Varsity Shirt',
            'is_active' => true,
            'has_variants' => true,
            'sku' => null,
            'price' => null,
        ]);
        $green = ProductVariant::factory()->for($product)->create(['name' => 'Green - L', 'sku' => 'VS-GL', 'price' => 350]);
        $white = ProductVariant::factory()->for($product)->create(['name' => 'White - L', 'sku' => 'VS-WL', 'price' => 350]);

        $this->storedImage($product, 'products/shared.jpg', attributes: ['is_primary' => true]);
        $this->storedImage($product, 'products/variants/green.jpg', $green);
        $this->storedImage($product, 'products/variants/white.jpg', $white);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 700,
            'total' => 700,
            'status' => 'completed',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $green->id,
            'product_name' => 'Varsity Shirt',
            'product_sku' => 'VS-GL',
            'variant_name' => 'Green - L',
            'product_image' => 'products/variants/green.jpg',
            'price' => 350,
            'quantity' => 2,
            'subtotal' => 700,
        ]);

        return compact('product', 'green', 'white', 'orderItem');
    }

    private const ALL_PATHS = ['products/shared.jpg', 'products/variants/green.jpg', 'products/variants/white.jpg'];

    // --- Form saves -------------------------------------------------------------

    public function test_removing_a_gallery_image_deletes_its_object_after_the_save(): void
    {
        $product = Product::factory()->create(['has_variants' => false, 'is_active' => true, 'price' => 100, 'stock_quantity' => 1]);
        $this->storedImage($product, 'products/front.jpg', attributes: ['is_primary' => true, 'sort_order' => 0]);
        $this->storedImage($product, 'products/back.jpg', attributes: ['sort_order' => 1]);
        Storage::disk('r2')->put('products/replacement.jpg', 'image-bytes');
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.generalImages', ['products/replacement.jpg', 'products/back.jpg'])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('r2')->assertMissing('products/front.jpg');
        Storage::disk('r2')->assertExists(['products/back.jpg', 'products/replacement.jpg']);
        $this->assertEqualsCanonicalizing(
            ['products/replacement.jpg', 'products/back.jpg'],
            $product->images()->pluck('image_path')->all(),
        );
    }

    public function test_removing_a_variant_deletes_its_images_and_objects(): void
    {
        ['product' => $product, 'green' => $green, 'white' => $white] = $this->catalogueWithHistory();
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.variants', [
                "record-{$green->id}" => [
                    'name' => 'Green - L',
                    'sku' => 'VS-GL',
                    'price' => 350,
                    'stock_quantity' => 5,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                    'images' => ['products/variants/green.jpg'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('product_variants', ['id' => $white->id]);
        $this->assertDatabaseMissing('product_images', ['image_path' => 'products/variants/white.jpg']);
        Storage::disk('r2')->assertMissing('products/variants/white.jpg');
        Storage::disk('r2')->assertExists(['products/shared.jpg', 'products/variants/green.jpg']);

        // The per-model delete leaves an audit row the database cascade never would.
        $this->assertTrue(Activity::query()
            ->where('subject_type', ProductImage::class)
            ->where('event', 'deleted')
            ->where('properties->old->image_path', 'products/variants/white.jpg')
            ->exists());
    }

    /**
     * Removing a variant (or just replacing its image) still deletes the row,
     * but must not take the R2 file with it while a cart or an order still
     * points at that exact picture -- otherwise the customer's cart, or the
     * order history, is left showing a broken <img> tag for a routine
     * catalogue edit that never touched the whole product. This is the
     * variant-image counterpart of test_removing_a_variant_deletes_its_images_and_objects,
     * which covers the same removal when nothing else references the file.
     *
     * The product here has no shared image, only per-variant ones -- like
     * catalogueWithHistory()'s shared photo, a shared image would let the
     * cart quietly fall back to it and mask the bug this guards against.
     */
    public function test_removing_a_variant_keeps_its_image_file_while_a_cart_or_order_still_references_it(): void
    {
        $product = Product::factory()->create([
            'name' => 'Glory & Honor Shirt',
            'is_active' => true,
            'has_variants' => true,
            'sku' => null,
            'price' => null,
        ]);
        $medium = ProductVariant::factory()->for($product)->create(['name' => 'Medium', 'sku' => 'GH-M', 'price' => 320]);
        $large = ProductVariant::factory()->for($product)->create(['name' => 'Large', 'sku' => 'GH-L', 'price' => 320]);
        $this->storedImage($product, 'products/variants/medium.jpg', $medium);
        $this->storedImage($product, 'products/variants/large.jpg', $large);

        $cart = Cart::create(['customer_id' => Customer::factory()->create()->id]);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $medium->id,
            'quantity' => 1,
            'product_image' => Storage::disk('r2')->url('products/variants/medium.jpg'),
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 320,
            'total' => 320,
            'status' => 'completed',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $medium->id,
            'product_name' => 'Glory & Honor Shirt',
            'product_sku' => 'GH-M',
            'variant_name' => 'Medium',
            'product_image' => Storage::disk('r2')->url('products/variants/medium.jpg'),
            'price' => 320,
            'quantity' => 1,
            'subtotal' => 320,
        ]);

        $this->actingAsAdmin();

        // The admin removes the Medium variant entirely, e.g. to retire that
        // size, leaving only Large.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.variants', [
                "record-{$large->id}" => [
                    'name' => 'Large',
                    'sku' => 'GH-L',
                    'price' => 320,
                    'stock_quantity' => 5,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                    'images' => ['products/variants/large.jpg'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // The variant and its image row are gone, same as without a cart or
        // order in the picture -- only the file itself is spared.
        $this->assertDatabaseMissing('product_variants', ['id' => $medium->id]);
        $this->assertDatabaseMissing('product_images', ['image_path' => 'products/variants/medium.jpg']);
        Storage::disk('r2')->assertExists('products/variants/medium.jpg');

        // Nothing live is left for this row -- no variant, no shared image --
        // so it falls back to the snapshot instead of rendering a broken
        // <img> tag or the generic letter placeholder.
        $this->assertSame(
            Storage::disk('r2')->url('products/variants/medium.jpg'),
            $cartItem->fresh()->display_image_url,
        );

        // The order line's own snapshot never depended on the live lookup,
        // but this confirms the file it points at is still there to load.
        $this->assertSame(
            Storage::disk('r2')->url('products/variants/medium.jpg'),
            $orderItem->fresh()->product_image,
        );
    }

    public function test_a_failed_save_keeps_every_file(): void
    {
        ['product' => $product, 'green' => $green] = $this->catalogueWithHistory();
        $this->actingAsAdmin();

        ProductVariant::saving(function (ProductVariant $variant) {
            if ($variant->sku === 'VS-GL') {
                throw new RuntimeException('Simulated failure mid-save');
            }
        });

        try {
            Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
                ->set('data.generalImages', [])
                ->set('data.variants', [
                    "record-{$green->id}" => [
                        'name' => 'Green - L',
                        'sku' => 'VS-GL',
                        'price' => 360,
                        'stock_quantity' => 5,
                        'low_stock_threshold' => 2,
                        'is_active' => true,
                        'images' => [],
                    ],
                ])
                ->call('save');
            $this->fail('The save should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure mid-save', $exception->getMessage());
        }

        $this->assertSame(3, ProductImage::where('product_id', $product->id)->count());
        $this->assertSame(2, $product->variants()->count());
        Storage::disk('r2')->assertExists(self::ALL_PATHS);
    }

    // --- Force delete -----------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function forceDeletePaths(): array
    {
        return [
            'forceDelete' => ['forceDelete'],
            'forceDeleteQuietly' => ['forceDeleteQuietly'],
        ];
    }

    #[DataProvider('forceDeletePaths')]
    public function test_force_delete_removes_the_product_its_children_and_all_media(string $method): void
    {
        ['product' => $product, 'green' => $green, 'white' => $white, 'orderItem' => $orderItem] = $this->catalogueWithHistory();

        $this->assertTrue($product->{$method}());

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertDatabaseMissing('product_variants', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('r2')->assertMissing(self::ALL_PATHS);

        // Order history survives with its snapshot; only the live links go.
        $orderItem->refresh();
        $this->assertNull($orderItem->product_id);
        $this->assertNull($orderItem->product_variant_id);
        $this->assertSame('Varsity Shirt', $orderItem->product_name);
        $this->assertSame('VS-GL', $orderItem->product_sku);
        $this->assertSame('Green - L', $orderItem->variant_name);
        $this->assertSame('products/variants/green.jpg', $orderItem->product_image);
        $this->assertEquals(350, $orderItem->price);
        $this->assertSame(2, $orderItem->quantity);
        $this->assertEquals(700, $orderItem->subtotal);
    }

    public function test_force_delete_fires_lifecycle_events_without_soft_deleting_first(): void
    {
        ['product' => $product] = $this->catalogueWithHistory();
        $events = [];

        Product::forceDeleting(function () use (&$events) {
            $events[] = 'forceDeleting';
        });
        Product::forceDeleted(function () use (&$events) {
            $events[] = 'forceDeleted';
        });
        Product::deleted(function (Product $deleted) use (&$events) {
            $events[] = $deleted->isForceDeleting() ? 'deleted:force' : 'deleted:soft';
        });
        Product::updated(function () use (&$events) {
            $events[] = 'updated';
        });

        $product->forceDelete();

        $this->assertSame(['forceDeleting', 'deleted:force', 'forceDeleted'], $events);
        $this->assertTrue(Activity::query()
            ->where('subject_type', ProductVariant::class)
            ->where('event', 'deleted')
            ->exists());
    }

    public function test_quiet_force_delete_fires_no_model_or_activity_events(): void
    {
        ['product' => $product] = $this->catalogueWithHistory();
        $events = [];
        Product::forceDeleting(function () use (&$events) {
            $events[] = 'forceDeleting';
        });
        Product::forceDeleted(function () use (&$events) {
            $events[] = 'forceDeleted';
        });
        $activitiesBefore = Activity::count();

        $product->forceDeleteQuietly();

        $this->assertSame([], $events);
        $this->assertSame($activitiesBefore, Activity::count());
        $this->assertNull(Product::withTrashed()->find($product->id));
        Storage::disk('r2')->assertMissing(self::ALL_PATHS);
    }

    #[DataProvider('forceDeletePaths')]
    public function test_a_path_still_referenced_by_another_product_is_kept(string $method): void
    {
        ['product' => $product] = $this->catalogueWithHistory();
        $other = Product::factory()->create();
        ProductImage::factory()->for($other)->create(['image_path' => 'products/shared.jpg']);

        $product->{$method}();

        Storage::disk('r2')->assertExists('products/shared.jpg');
        Storage::disk('r2')->assertMissing(['products/variants/green.jpg', 'products/variants/white.jpg']);
    }

    #[DataProvider('forceDeletePaths')]
    public function test_a_rolled_back_force_delete_keeps_rows_and_media(string $method): void
    {
        ['product' => $product, 'orderItem' => $orderItem] = $this->catalogueWithHistory();

        // A table that refuses to let the product row go. The children are
        // deleted first, so this proves they come back with it on rollback --
        // and it fails on both paths, since withoutEvents() can't bypass a
        // database constraint.
        Schema::create('product_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
        });
        DB::table('product_holds')->insert(['product_id' => $product->id]);

        try {
            $product->{$method}();
            $this->fail('The force delete should have been refused.');
        } catch (QueryException) {
            // expected
        }

        $this->assertNotNull(Product::withTrashed()->find($product->id));
        $this->assertSame(2, ProductVariant::where('product_id', $product->id)->count());
        $this->assertSame(3, ProductImage::where('product_id', $product->id)->count());
        $this->assertSame($product->id, $orderItem->fresh()->product_id);
        Storage::disk('r2')->assertExists(self::ALL_PATHS);

        // Not part of any migration: left in place, it would block
        // DatabaseMigrations from dropping `products` after the test.
        Schema::drop('product_holds');
    }

    public function test_a_listener_failure_rolls_back_the_normal_force_delete(): void
    {
        ['product' => $product] = $this->catalogueWithHistory();
        Product::forceDeleted(fn () => throw new RuntimeException('Simulated failure after delete'));

        try {
            $product->forceDelete();
            $this->fail('The force delete should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after delete', $exception->getMessage());
        }

        $this->assertNotNull(Product::withTrashed()->find($product->id));
        $this->assertSame(3, ProductImage::where('product_id', $product->id)->count());
        Storage::disk('r2')->assertExists(self::ALL_PATHS);
    }

    public function test_the_admin_force_delete_action_cleans_up_media(): void
    {
        ['product' => $product, 'orderItem' => $orderItem] = $this->catalogueWithHistory();
        $product->delete();
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(ForceDeleteAction::class);

        $this->assertNull(Product::withTrashed()->find($product->id));
        Storage::disk('r2')->assertMissing(self::ALL_PATHS);
        $this->assertNull($orderItem->fresh()->product_id);
    }
}
