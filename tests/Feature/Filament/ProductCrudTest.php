<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\UniqueProductName;
use App\Rules\UniqueSku;
use App\Support\Name;
use App\Support\Sku;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Product create/read/update through the admin form and the storefront.
 *
 * Media cleanup and force deletion run their R2 deletes after commit, so
 * they are covered in ProductMediaCleanupTest without RefreshDatabase's
 * wrapping transaction.
 */
class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private \Closure $undoRepeaterFake;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        // Numeric repeater keys (variants.0.sku) instead of random UUIDs.
        $this->undoRepeaterFake = Repeater::fake();
    }

    protected function tearDown(): void
    {
        ($this->undoRepeaterFake)();
        Carbon::setTestNow();

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
        // User::canAccessPanel() needs one of the panel roles.
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        // Authorization is not what these tests are about.
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    private function simpleProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 250,
            'stock_quantity' => 10,
            ...$attributes,
        ]);
    }

    private function variantProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'has_variants' => true,
            'sku' => null,
            'price' => null,
            'stock_quantity' => 0,
            ...$attributes,
        ]);
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->for($product)->create([
            'price' => 300,
            'stock_quantity' => 5,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function simpleFormData(array $overrides = []): array
    {
        return [
            'name' => 'CLSU Tumbler',
            'category_id' => Category::factory()->create()->id,
            'sku' => 'TMB-001',
            'price' => 450,
            'stock_quantity' => 12,
            'low_stock_threshold' => 3,
            ...$overrides,
        ];
    }

    private function variantRow(array $overrides = []): array
    {
        return [
            'name' => 'Green - Large',
            'sku' => 'SHIRT-GL',
            'price' => 350,
            'stock_quantity' => 8,
            'low_stock_threshold' => 2,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function variantFormData(array $variants, array $overrides = []): array
    {
        return [
            'name' => 'Varsity Shirt',
            'category_id' => Category::factory()->create()->id,
            'has_variants' => true,
            'variants' => $variants,
            ...$overrides,
        ];
    }

    // --- Create -------------------------------------------------------------

    public function test_a_valid_simple_product_can_be_created(): void
    {
        $this->actingAsAdmin();
        $data = $this->simpleFormData();

        Livewire::test(CreateProduct::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::sole();
        $this->assertSame('CLSU Tumbler', $product->name);
        $this->assertSame('clsu-tumbler', $product->slug);
        $this->assertSame('TMB-001', $product->sku);
        $this->assertEquals(450, $product->price);
        $this->assertSame(12, $product->stock_quantity);
        $this->assertSame($data['category_id'], $product->category_id);
        $this->assertFalse($product->has_variants);
    }

    public function test_a_valid_variant_product_can_be_created_without_a_product_sku(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([
                $this->variantRow(),
                $this->variantRow(['name' => 'Green - Small', 'sku' => 'SHIRT-GS', 'price' => 320]),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::sole();
        $this->assertTrue($product->has_variants);
        // The Pricing tab is hidden, so no hidden product-level identifier.
        $this->assertNull($product->sku);
        $this->assertEqualsCanonicalizing(['SHIRT-GL', 'SHIRT-GS'], $product->variants()->pluck('sku')->all());
    }

    public function test_new_products_default_to_active(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->assertSchemaStateSet(['is_active' => true, 'is_featured' => false, 'has_variants' => false])
            ->fillForm($this->simpleFormData())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Product::sole()->is_active);
    }

    public function test_a_simple_product_requires_its_fields(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => null,
                'category_id' => null,
                'sku' => null,
                'price' => null,
                'stock_quantity' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'category_id' => 'required',
                'sku' => 'required',
                'price' => 'required',
                'stock_quantity' => 'required',
            ]);

        $this->assertSame(0, Product::count());
    }

    public function test_a_simple_product_rejects_negative_price_and_stock(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['price' => -1, 'stock_quantity' => -5]))
            ->call('create')
            ->assertHasFormErrors(['price' => 'min', 'stock_quantity' => 'min']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_variant_product_requires_at_least_one_variant(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([]))
            ->call('create')
            ->assertHasFormErrors(['variants']);

        $this->assertSame(0, Product::count());
    }

    public function test_each_variant_requires_name_sku_and_non_negative_price_and_stock(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([
                $this->variantRow(['name' => null, 'sku' => null, 'price' => null, 'stock_quantity' => null]),
                $this->variantRow(['sku' => 'SHIRT-NEG', 'price' => -1, 'stock_quantity' => -1]),
            ]))
            ->call('create')
            ->assertHasFormErrors([
                'variants.0.name' => 'required',
                'variants.0.sku' => 'required',
                'variants.0.price' => 'required',
                'variants.0.stock_quantity' => 'required',
                'variants.1.price' => 'min',
                'variants.1.stock_quantity' => 'min',
            ]);

        $this->assertSame(0, Product::count());
    }

    // --- SKU uniqueness -----------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function collidingSkus(): array
    {
        return [
            'exact' => ['ABC-001'],
            'case only' => ['abc-001'],
            'whitespace only' => [' ABC-001 '],
            'case and whitespace' => [' abc-001 '],
        ];
    }

    #[DataProvider('collidingSkus')]
    public function test_a_product_sku_cannot_reuse_another_products_sku(string $submitted): void
    {
        $this->simpleProduct(['sku' => 'ABC-001']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['sku' => $submitted]))
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    #[DataProvider('collidingSkus')]
    public function test_a_product_sku_cannot_reuse_a_variant_sku(string $submitted): void
    {
        $this->variant($this->variantProduct(), ['sku' => 'ABC-001']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['sku' => $submitted]))
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    #[DataProvider('collidingSkus')]
    public function test_a_variant_sku_cannot_reuse_another_variant_sku(string $submitted): void
    {
        $this->variant($this->variantProduct(), ['sku' => 'ABC-001']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([$this->variantRow(['sku' => $submitted])]))
            ->call('create')
            ->assertHasFormErrors(['variants.0.sku']);
    }

    #[DataProvider('collidingSkus')]
    public function test_a_variant_sku_cannot_reuse_a_product_sku(string $submitted): void
    {
        $this->simpleProduct(['sku' => 'ABC-001']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([$this->variantRow(['sku' => $submitted])]))
            ->call('create')
            ->assertHasFormErrors(['variants.0.sku']);
    }

    public function test_a_soft_deleted_products_sku_still_blocks_reuse(): void
    {
        $this->simpleProduct(['sku' => 'ABC-001'])->delete();
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['sku' => 'abc-001']))
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    public function test_legacy_padded_mixed_case_skus_are_compared_normalized(): void
    {
        // Written around the model hook, as a pre-sanitization row would be.
        $product = $this->simpleProduct();
        DB::table('products')->where('id', $product->id)->update(['sku' => '  Abc-001  ']);

        $this->assertFalse(validator(['sku' => 'ABC-001'], ['sku' => [new UniqueSku]])->passes());
        $this->assertTrue(validator(['sku' => 'ABC-001'], ['sku' => [new UniqueSku(ignoreProductId: $product->id)]])->passes());
    }

    #[DataProvider('collidingSkus')]
    public function test_duplicate_variant_rows_in_one_submission_are_rejected(string $second): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([
                $this->variantRow(['sku' => 'ABC-001']),
                $this->variantRow(['name' => 'Green - Small', 'sku' => $second]),
            ]))
            ->call('create')
            ->assertHasFormErrors(['variants.0.sku', 'variants.1.sku']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_single_variant_row_does_not_collide_with_itself(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([$this->variantRow(['sku' => 'ABC-001'])]))
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_skus_are_stored_trimmed_with_their_case_preserved(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['sku' => '  Abc-001  ']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Abc-001', Product::sole()->sku);

        $variant = $this->variant($this->variantProduct(), ['sku' => ' Var-Xy ']);
        $this->assertSame('Var-Xy', $variant->fresh()->sku);

        $blank = $this->simpleProduct(['sku' => '   ']);
        $this->assertNull($blank->fresh()->sku);
    }

    public function test_sku_helpers_keep_storage_and_comparison_separate(): void
    {
        $this->assertSame('Abc-001', Sku::sanitizeForStorage(' Abc-001 '));
        $this->assertSame('', Sku::sanitizeForStorage('   '));
        $this->assertNull(Sku::sanitizeForStorage('   ', blankToNull: true));
        $this->assertSame('abc-001', Sku::comparisonKey(' Abc-001 '));
        $this->assertNull(Sku::comparisonKey('  '));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function collidingNames(): array
    {
        return [
            'exact' => ['CLSU Tumbler'],
            'case only' => ['clsu tumbler'],
            'whitespace only' => ['  CLSU Tumbler  '],
            'inner spacing' => ['CLSU   Tumbler'],
        ];
    }

    #[DataProvider('collidingNames')]
    public function test_a_product_name_cannot_reuse_another_products_name(string $submitted): void
    {
        $this->simpleProduct(['name' => 'CLSU Tumbler']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['name' => $submitted]))
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Product::count());
    }

    public function test_a_soft_deleted_products_name_still_blocks_reuse(): void
    {
        // A restore would put the second "CLSU Tumbler" back on the
        // storefront, so the name stays taken while the product is hidden.
        // The admin cannot see that product in the list, so the message says
        // which case they hit.
        $this->simpleProduct(['name' => 'CLSU Tumbler'])->delete();
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['name' => 'clsu tumbler']))
            ->call('create')
            ->assertHasFormErrors(['name' => UniqueProductName::TRASHED_MESSAGE]);
    }

    public function test_editing_keeps_a_products_own_name_valid(): void
    {
        $product = $this->simpleProduct(['name' => 'CLSU Tumbler']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['stock_quantity' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('CLSU Tumbler', $product->fresh()->name);
    }

    #[DataProvider('collidingNames')]
    public function test_a_products_name_cannot_be_edited_onto_another_products_name(string $submitted): void
    {
        $this->simpleProduct(['name' => 'CLSU Tumbler']);
        $product = $this->simpleProduct(['name' => 'CLSU Lanyard', 'sku' => 'LAN-001']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => $submitted])
            ->call('save')
            ->assertHasFormErrors(['name' => UniqueProductName::MESSAGE]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function collidingVariantNames(): array
    {
        return [
            'exact' => ['Medium'],
            'case only' => ['medium'],
            'whitespace only' => ['  Medium  '],
            'inner spacing' => ['Extra  Large'],
        ];
    }

    #[DataProvider('collidingVariantNames')]
    public function test_duplicate_variant_names_in_one_submission_are_rejected(string $second): void
    {
        $this->actingAsAdmin();

        // The first row carries the squished form of whatever the second row
        // submits, so every provider case is the same variant name typed twice.
        $first = trim(preg_replace('/\s+/', ' ', $second));

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([
                $this->variantRow(['name' => $first, 'sku' => 'SHIRT-M']),
                $this->variantRow(['name' => $second, 'sku' => 'SHIRT-M2']),
            ]))
            ->call('create')
            ->assertHasFormErrors(['variants.0.name', 'variants.1.name']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_single_variant_row_name_does_not_collide_with_itself(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData([$this->variantRow(['name' => 'Medium'])]))
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_the_same_variant_name_can_be_used_by_different_products(): void
    {
        // "Medium" is only ambiguous inside one product's picker; every shirt
        // in the catalog is allowed to have one.
        $this->variant($this->variantProduct(['name' => 'Varsity Shirt']), ['name' => 'Medium', 'sku' => 'VS-M']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->variantFormData(
                [$this->variantRow(['name' => 'Medium', 'sku' => 'PE-M'])],
                ['name' => 'PE Shirt'],
            ))
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_a_variant_name_is_free_again_when_its_row_is_removed_in_the_same_save(): void
    {
        // Retyping a row the admin just deleted is a correction, not a
        // duplicate: only the submitted rows are compared, never the siblings
        // still in the database mid-save.
        $product = $this->variantProduct();
        $this->variant($product, ['name' => 'Medium', 'sku' => 'OLD-M']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['variants' => [$this->variantRow(['name' => 'Medium', 'sku' => 'NEW-M'])]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['NEW-M'], $product->variants()->pluck('sku')->all());
    }

    public function test_names_are_stored_squished_with_their_case_preserved(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['name' => '  CLSU   Tumbler  ']))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::sole();
        $this->assertSame('CLSU Tumbler', $product->name);
        // The slug is built after the squish, so no empty segment lands in it.
        $this->assertSame('clsu-tumbler', $product->slug);

        $variant = $this->variant($this->variantProduct(), ['name' => ' Extra  Large ']);
        $this->assertSame('Extra Large', $variant->fresh()->name);
    }

    public function test_name_helpers_keep_storage_and_comparison_separate(): void
    {
        $this->assertSame('Extra Large', Name::sanitizeForStorage(' Extra  Large '));
        $this->assertSame('', Name::sanitizeForStorage('   '));
        $this->assertNull(Name::sanitizeForStorage('   ', blankToNull: true));
        $this->assertSame('extra large', Name::comparisonKey(' Extra  Large '));
        $this->assertNull(Name::comparisonKey('  '));
    }

    public function test_editing_keeps_an_unchanged_sku_valid(): void
    {
        $product = $this->simpleProduct(['sku' => 'ABC-001']);
        $variantProduct = $this->variantProduct();
        $variant = $this->variant($variantProduct, ['sku' => 'VAR-001']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditProduct::class, ['record' => $variantProduct->getRouteKey()])
            ->fillForm(['name' => 'Renamed Shirt'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('VAR-001', $variant->fresh()->sku);
    }

    // --- Slugs ---------------------------------------------------------------

    public function test_slugs_get_deterministic_suffixes_including_against_trashed_products(): void
    {
        $first = Product::factory()->create(['name' => 'CLSU Shirt', 'slug' => null]);
        $second = Product::factory()->create(['name' => 'CLSU Shirt', 'slug' => null]);
        $second->delete();
        $third = Product::factory()->create(['name' => 'CLSU  shirt!', 'slug' => null]);

        $this->assertSame('clsu-shirt', $first->slug);
        $this->assertSame('clsu-shirt-2', $second->slug);
        $this->assertSame('clsu-shirt-3', $third->slug);
    }

    public function test_a_name_with_no_slug_characters_falls_back(): void
    {
        $product = Product::factory()->create(['name' => '!!!', 'slug' => null]);

        $this->assertSame('product', $product->slug);
    }

    public function test_renaming_a_product_keeps_its_slug(): void
    {
        $product = $this->simpleProduct(['name' => 'CLSU Shirt', 'slug' => 'clsu-shirt']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'CLSU Shirt 2026 Edition'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('CLSU Shirt 2026 Edition', $product->name);
        $this->assertSame('clsu-shirt', $product->slug);
    }

    public function test_the_edit_form_does_not_expose_the_slug(): void
    {
        // The field used to render read-only on edit. A read-only TextInput is
        // only an HTML attribute, though: fillForm() sets component state
        // directly and sailed straight past it, so the column was writable by
        // a crafted request. Leaving the field out of the schema is what
        // actually closes that -- Filament saves the schema's fields, not
        // whatever arrives in the payload.
        $product = $this->simpleProduct(['slug' => 'original']);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['slug' => 'tampered-address'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('original', $product->fresh()->slug);
    }

    // --- Update ---------------------------------------------------------------

    public function test_editing_price_category_and_stock(): void
    {
        $product = $this->simpleProduct();
        $newCategory = Category::factory()->create();
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price' => 199.5, 'category_id' => $newCategory->id, 'stock_quantity' => 42])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertEquals(199.5, $product->price);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame(42, $product->stock_quantity);
    }

    public function test_editing_adding_and_removing_variants(): void
    {
        $product = $this->variantProduct();
        $kept = $this->variant($product, ['name' => 'Green - Large', 'sku' => 'KEEP-1', 'sort_order' => 0]);
        $removed = $this->variant($product, ['name' => 'Green - Small', 'sku' => 'DROP-1', 'sort_order' => 1]);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            // set(), not fillForm(): fillForm() writes leaf by leaf, which
            // merges into the existing rows instead of dropping the removed one.
            ->set('data.variants', [
                "record-{$kept->id}" => $this->variantRow(['name' => 'Green - Large', 'sku' => 'KEEP-1', 'price' => 399, 'stock_quantity' => 3]),
                0 => $this->variantRow(['name' => 'Green - XL', 'sku' => 'NEW-1']),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $kept->refresh();
        $this->assertEquals(399, $kept->price);
        $this->assertSame(3, $kept->stock_quantity);
        $this->assertDatabaseMissing('product_variants', ['id' => $removed->id]);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'NEW-1']);
    }

    public function test_editing_images_replaces_the_gallery(): void
    {
        $product = $this->simpleProduct();
        $old = ProductImage::factory()->for($product)->create(['image_path' => 'products/old.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $keep = ProductImage::factory()->for($product)->create(['image_path' => 'products/keep.jpg', 'is_primary' => false, 'sort_order' => 1]);
        Storage::disk('r2')->put('products/new.jpg', 'new');
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.generalImages', ['products/keep.jpg', 'products/new.jpg'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('product_images', ['id' => $old->id]);
        $this->assertTrue($keep->fresh()->is_primary);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'image_path' => 'products/new.jpg', 'sort_order' => 1]);
    }

    // --- Product type lock ------------------------------------------------------

    public function test_the_product_type_toggle_is_disabled_on_edit(): void
    {
        $product = $this->simpleProduct();
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldDisabled('has_variants');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldEnabled('has_variants');
    }

    public function test_an_edit_submission_cannot_change_the_product_type(): void
    {
        $product = $this->simpleProduct(['sku' => 'SIMPLE-1', 'price' => 250, 'stock_quantity' => 10]);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['has_variants' => true])
            ->call('save');

        $product->refresh();
        $this->assertFalse($product->has_variants);
        $this->assertSame('SIMPLE-1', $product->sku);
        $this->assertEquals(250, $product->price);
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_the_model_refuses_to_change_the_product_type_in_either_direction(): void
    {
        $simple = $this->simpleProduct();
        $variable = $this->variantProduct();

        foreach ([[$simple, true], [$variable, false]] as [$product, $target]) {
            try {
                $product->update(['has_variants' => $target]);
                $this->fail('Changing has_variants should have been refused.');
            } catch (ValidationException $exception) {
                $this->assertSame([Product::TYPE_LOCKED_MESSAGE], $exception->errors()['has_variants']);
            }

            $this->assertSame(! $target, (bool) $product->fresh()->has_variants);
        }
    }

    // --- Scoped transactions ------------------------------------------------

    public function test_only_the_product_pages_enable_database_transactions(): void
    {
        $this->actingAsAdmin();

        // Instantiating the classes is what would fatal on a bad trait
        // property redeclaration; php -l does not load traits.
        $this->assertTrue(app(CreateProduct::class)->hasDatabaseTransactions());
        $this->assertTrue(app(EditProduct::class)->hasDatabaseTransactions());
        $this->assertFalse(app(EditCategory::class)->hasDatabaseTransactions());

        Livewire::test(CreateProduct::class)->assertOk();
        Livewire::test(EditProduct::class, ['record' => $this->simpleProduct()->getRouteKey()])->assertOk();
    }

    public function test_a_failed_save_rolls_back_the_product_variants_and_images(): void
    {
        $product = $this->variantProduct(['name' => 'Original']);
        $kept = $this->variant($product, ['sku' => 'KEEP-1']);
        $removed = $this->variant($product, ['sku' => 'DROP-1']);
        $image = ProductImage::factory()->for($product)->create(['image_path' => 'products/gallery.jpg']);
        $this->actingAsAdmin();

        // Fails after the product row and the removed variant have been
        // written, while the repeater saves the surviving variant.
        ProductVariant::saving(function (ProductVariant $variant) {
            if ($variant->sku === 'KEEP-1') {
                throw new RuntimeException('Simulated failure mid-save');
            }
        });

        try {
            Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
                ->fillForm(['name' => 'Changed'])
                ->set('data.generalImages', [])
                ->set('data.variants', ["record-{$kept->id}" => $this->variantRow(['sku' => 'KEEP-1', 'price' => 999])])
                ->call('save');
            $this->fail('The save should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure mid-save', $exception->getMessage());
        }

        $this->assertSame('Original', $product->fresh()->name);
        $this->assertDatabaseHas('product_variants', ['id' => $removed->id]);
        $this->assertNotEquals(999, $kept->fresh()->price);
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    // --- Storefront -----------------------------------------------------------

    public function test_the_public_page_serves_only_active_non_deleted_products(): void
    {
        $active = $this->simpleProduct(['slug' => 'on-sale']);
        $this->simpleProduct(['slug' => 'unpublished', 'is_active' => false]);
        $this->simpleProduct(['slug' => 'removed'])->delete();

        Livewire::test(ProductDetails::class, ['slug' => 'on-sale'])
            ->assertOk()
            ->assertSee($active->name);

        $this->get(route('products.show', 'unpublished'))->assertNotFound();
        $this->get(route('products.show', 'removed'))->assertNotFound();
    }

    public function test_storefront_search_matches_an_active_variant_sku_only(): void
    {
        $product = $this->variantProduct(['name' => 'Varsity Jacket']);
        $this->variant($product, ['sku' => 'JKT-GREEN-L']);
        $hidden = $this->variantProduct(['name' => 'Retired Cap']);
        $this->variant($hidden, ['sku' => 'CAP-OLD-1', 'is_active' => false]);

        Livewire::test(ProductListing::class)
            ->set('search', 'JKT-GREEN')
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$product->id]);

        Livewire::test(ProductListing::class)
            ->set('search', 'CAP-OLD')
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_a_page_view_does_not_change_updated_at(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $product = $this->simpleProduct(['views_count' => 5]);
        $updatedAt = $product->fresh()->updated_at;

        Carbon::setTestNow('2026-09-10 17:30:00');
        $product->incrementViews();

        $product = $product->fresh();
        $this->assertSame(6, $product->views_count);
        $this->assertTrue($updatedAt->equalTo($product->updated_at));
    }

    // --- Soft delete ------------------------------------------------------------

    public function test_soft_delete_and_restore_keep_images(): void
    {
        $product = $this->variantProduct();
        $variant = $this->variant($product);
        Storage::disk('r2')->put('products/shared.jpg', 'x');
        Storage::disk('r2')->put('products/variants/green.jpg', 'x');
        ProductImage::factory()->for($product)->create(['image_path' => 'products/shared.jpg']);
        ProductImage::factory()->for($product)->create(['image_path' => 'products/variants/green.jpg', 'product_variant_id' => $variant->id]);

        $product->delete();
        $this->assertSoftDeleted($product);
        $this->assertSame(2, ProductImage::where('product_id', $product->id)->count());

        $product->restore();
        $this->assertNotSoftDeleted($product);
        $this->assertSame(2, ProductImage::where('product_id', $product->id)->count());
        Storage::disk('r2')->assertExists(['products/shared.jpg', 'products/variants/green.jpg']);
    }

    public function test_an_active_product_cannot_be_created_in_an_inactive_category(): void
    {
        $inactive = Category::factory()->create(['is_active' => false]);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['category_id' => $inactive->id, 'is_active' => true]))
            ->call('create')
            ->assertHasFormErrors(['category_id']);

        $this->assertSame(0, Product::count());
    }

    public function test_an_inactive_product_can_be_filed_under_an_inactive_category(): void
    {
        $inactive = Category::factory()->create(['is_active' => false]);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->simpleFormData(['category_id' => $inactive->id, 'is_active' => false]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Product::where('category_id', $inactive->id)->count());
    }

    public function test_the_model_refuses_active_products_in_inactive_categories(): void
    {
        $inactive = Category::factory()->create(['is_active' => false]);
        $moved = $this->simpleProduct();
        $activated = $this->simpleProduct(['category_id' => $inactive->id, 'is_active' => false]);

        foreach ([
            fn () => $moved->update(['category_id' => $inactive->id]),
            fn () => $activated->update(['is_active' => true]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('An active product in an inactive category should have been refused.');
            } catch (ValidationException $exception) {
                $this->assertSame([Product::INACTIVE_CATEGORY_MESSAGE], $exception->errors()['category_id']);
            }
        }

        $this->assertNotSame($inactive->id, $moved->fresh()->category_id);
        $this->assertFalse($activated->fresh()->is_active);
    }

    public function test_unrelated_edits_do_not_trip_over_an_existing_inactive_category(): void
    {
        // Data from before the rule existed can already hold an active
        // product in an inactive category; editing its price must still work.
        $inactive = Category::factory()->create(['is_active' => false]);
        $product = $this->simpleProduct();
        Product::query()->whereKey($product->id)->update(['category_id' => $inactive->id]);

        $product->fresh()->update(['price' => 300]);

        $this->assertEquals(300, $product->fresh()->price);
    }

    public function test_a_restored_product_comes_back_inactive(): void
    {
        $product = $this->simpleProduct(['is_active' => true]);

        $product->delete();
        // Trashing leaves is_active alone; only restoring changes it.
        $this->assertTrue($product->fresh()->is_active);

        $product->restore();
        $this->assertNotSoftDeleted($product);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_bulk_restore_brings_products_back_inactive(): void
    {
        $products = collect([
            $this->simpleProduct(['is_active' => true]),
            $this->simpleProduct(['is_active' => true]),
        ]);
        $products->each->delete();
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'trash')
            ->callTableBulkAction('restore', $products);

        $products->each(function (Product $product) {
            $this->assertNotSoftDeleted($product);
            $this->assertFalse($product->fresh()->is_active);
        });
    }

    public function test_restoring_from_the_edit_page_refreshes_the_active_toggle(): void
    {
        $product = $this->simpleProduct(['is_active' => true]);
        $product->delete();
        $this->actingAsAdmin();

        // Saving right after the restore must not republish the product from
        // the toggle state the form was filled with before it.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('restore')
            ->assertSchemaStateSet(['is_active' => false])
            ->call('save');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_the_soft_delete_action_is_labelled_move_to_trash(): void
    {
        $product = $this->simpleProduct();
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHasLabel('delete', 'Move to trash')
            ->callAction('delete');

        $this->assertSoftDeleted($product);
    }

    // --- Audit ------------------------------------------------------------------

    public function test_the_sku_audit_reports_every_conflict_type_without_changing_data(): void
    {
        $this->simpleProduct(['sku' => 'DUP-P']);
        $trashed = $this->simpleProduct();
        DB::table('products')->where('id', $trashed->id)->update(['sku' => ' dup-p ', 'deleted_at' => now()]);

        $this->variant($this->variantProduct(), ['sku' => 'DUP-V']);
        $variant = $this->variant($this->variantProduct());
        DB::table('product_variants')->where('id', $variant->id)->update(['sku' => 'dup-v']);

        $this->simpleProduct(['sku' => 'CROSS-1']);
        $this->variant($this->variantProduct(), ['sku' => 'Cross-1']);

        $blank = $this->simpleProduct();
        DB::table('products')->where('id', $blank->id)->update(['sku' => null]);
        $this->variantProduct(['name' => 'Stale Parent', 'sku' => 'STALE-PARENT']);

        $before = [DB::table('products')->get()->toArray(), DB::table('product_variants')->get()->toArray()];

        $exitCode = Artisan::call('products:audit-skus');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"DUP-P"', $output);
        $this->assertStringContainsString('" dup-p "', $output);
        $this->assertStringContainsString('(deleted)', $output);
        $this->assertStringContainsString('"dup-v"', $output);
        $this->assertStringContainsString('"Cross-1"', $output);
        $this->assertStringContainsString('NULL', $output);
        $this->assertStringContainsString('"STALE-PARENT"', $output);
        $this->assertEquals($before, [DB::table('products')->get()->toArray(), DB::table('product_variants')->get()->toArray()]);
    }

    public function test_the_sku_audit_passes_on_clean_data(): void
    {
        $this->simpleProduct(['sku' => 'CLEAN-1']);
        $this->variant($this->variantProduct(), ['sku' => 'CLEAN-2']);

        $this->assertSame(0, Artisan::call('products:audit-skus'));
        $this->assertStringContainsString('No SKU conflicts found.', Artisan::output());
    }
}
