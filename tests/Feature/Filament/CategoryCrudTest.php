<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Rules\UniqueCategoryName;
use Database\Seeders\CategorySeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Categories are flat, slugged from their name, and must never take a
 * product down with them: deletion is refused while any product row --
 * active, inactive, or soft-deleted -- still references the category.
 *
 * Image cleanup runs after commit, so it is covered in
 * CategoryImageCleanupTest without RefreshDatabase's wrapping transaction.
 */
class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
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

    private function productIn(Category $category, array $attributes = []): Product
    {
        return Product::factory()->for($category)->create($attributes);
    }

    // --- Schema, factory, seeders ------------------------------------------

    public function test_fresh_schema_and_factory_agree_that_categories_have_no_description(): void
    {
        $this->assertFalse(Schema::hasColumn('categories', 'description'));

        $category = Category::factory()->create();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_category_seeder_runs_on_a_fresh_database(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', ['slug' => 'fashion-apparel']);
    }

    // --- Create / update -----------------------------------------------------

    public function test_creating_a_category_generates_its_slug(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Fashion & Apparel', 'is_active' => true, 'sort_order' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Fashion & Apparel', 'slug' => 'fashion-apparel']);
    }

    public function test_an_inactive_category_can_be_created_and_updated(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Seasonal', 'is_active' => false, 'sort_order' => 3])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug', 'seasonal')->firstOrFail();
        $this->assertFalse((bool) $category->is_active);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['sort_order' => 7])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertFalse((bool) $category->is_active);
        $this->assertSame(7, (int) $category->sort_order);
    }

    public function test_renaming_a_category_does_not_change_its_slug(): void
    {
        // Slugs are set once, at creation, and never follow later renames
        // (same rule as Product::boot()): the slug is the category's
        // storefront filter URL, and regenerating it on every name change
        // would break links that were already shared.
        $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name' => 'Brand New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('old-name', $category->refresh()->slug);
        $this->assertSame('Brand New Name', $category->name);
    }

    public function test_a_duplicate_generated_slug_is_rejected(): void
    {
        Category::factory()->create(['name' => 'Gift Set', 'slug' => 'gift-set']);
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'gift  SET!', 'is_active' => true, 'sort_order' => 0])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Category::count());
    }

    /**
     * The product form's category dropdown can create a category inline,
     * which is a second way into the same table: without these rules it
     * happily made the "Gift Set" the Categories page would have refused.
     */
    public function test_the_inline_create_category_modal_rejects_a_duplicate(): void
    {
        Category::factory()->create(['name' => 'Gift Set', 'slug' => 'gift-set']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->callAction(
                TestAction::make('createOption')->schemaComponent('category_id'),
                ['name' => 'gift  SET!'],
            )
            ->assertHasActionErrors(['name' => UniqueCategoryName::messageFor('Gift Set')]);

        $this->assertSame(1, Category::count());
    }

    public function test_the_inline_create_category_modal_rejects_a_name_with_no_letters_or_numbers(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->callAction(
                TestAction::make('createOption')->schemaComponent('category_id'),
                ['name' => '!!!'],
            )
            ->assertHasActionErrors(['name' => Category::EMPTY_SLUG_MESSAGE]);

        $this->assertSame(0, Category::count());
    }

    public function test_the_inline_create_category_modal_still_creates_a_new_category(): void
    {
        Category::factory()->create(['name' => 'Gift Set', 'slug' => 'gift-set']);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->callAction(
                TestAction::make('createOption')->schemaComponent('category_id'),
                ['name' => 'Athletics'],
            )
            ->assertHasNoActionErrors();

        $athletics = Category::query()->where('slug', 'athletics')->sole();
        $this->assertSame('Athletics', $athletics->name);
    }

    public function test_a_name_with_no_letters_or_numbers_is_rejected_by_the_form(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => '!!!', 'is_active' => true, 'sort_order' => 0])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(0, Category::count());
    }

    public function test_renaming_to_a_name_with_no_letters_or_numbers_is_rejected_by_the_form(): void
    {
        $category = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name' => '???'])
            ->call('save')
            ->assertHasFormErrors(['name']);

        $category->refresh();
        $this->assertSame('Merch', $category->name);
        $this->assertSame('merch', $category->slug);
    }

    public function test_the_model_refuses_to_create_a_category_with_an_empty_slug(): void
    {
        try {
            Category::create(['name' => '!!!']);
            $this->fail('Expected a ValidationException for an empty slug.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->assertSame(0, Category::count());
    }

    public function test_the_model_allows_renaming_an_existing_category_to_an_empty_slugifying_name(): void
    {
        // The empty-slug guard only matters while a slug is being generated,
        // which now happens once, at creation. A rename never touches the
        // slug, so it can't leave one blank -- the Filament form's own rule
        // is what actually keeps "!!!" out of the UI (see
        // test_renaming_to_a_name_with_no_letters_or_numbers_is_rejected_by_the_form).
        $category = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);

        $category->update(['name' => '!!!']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => '!!!', 'slug' => 'merch']);
    }

    // --- Edit-page delete ----------------------------------------------------

    public function test_an_unassigned_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_a_category_with_an_active_product_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);
        $product = $this->productIn($category, ['is_active' => true]);
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('"Merch" can\'t be deleted');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id, 'deleted_at' => null]);
    }

    public function test_a_category_with_an_inactive_product_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);
        $product = $this->productIn($category, ['is_active' => false]);
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('"Merch" can\'t be deleted');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false, 'deleted_at' => null]);
    }

    public function test_a_category_referenced_only_by_a_soft_deleted_product_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);
        $product = $this->productIn($category);
        $product->delete();
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('"Merch" can\'t be deleted');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertSoftDeleted('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    // --- Bulk delete ---------------------------------------------------------

    public function test_bulk_delete_deletes_nothing_when_any_selected_category_has_products(): void
    {
        $empty = Category::factory()->create(['name' => 'Empty', 'slug' => 'empty']);
        $active = Category::factory()->create(['name' => 'Merch', 'slug' => 'merch']);
        $trashed = Category::factory()->create(['name' => 'Athletics', 'slug' => 'athletics']);
        $activeProduct = $this->productIn($active);
        $trashedProduct = $this->productIn($trashed);
        $trashedProduct->delete();
        $this->actingAsAdmin();

        Livewire::test(ListCategories::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$empty, $active, $trashed])
            ->assertNotified('2 categories can\'t be deleted');

        // All or nothing: even the unassigned category survives.
        $this->assertSame(3, Category::whereKey([$empty->id, $active->id, $trashed->id])->count());
        $this->assertDatabaseHas('products', ['id' => $activeProduct->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('products', ['id' => $trashedProduct->id]);
    }

    public function test_bulk_delete_removes_unassigned_categories(): void
    {
        $first = Category::factory()->create();
        $second = Category::factory()->create();
        $this->actingAsAdmin();

        Livewire::test(ListCategories::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$first, $second]);

        $this->assertSame(0, Category::whereKey([$first->id, $second->id])->count());
    }

    // --- Database protection -------------------------------------------------

    public function test_the_database_refuses_to_delete_a_category_with_products(): void
    {
        $category = Category::factory()->create();
        $product = $this->productIn($category);
        $product->delete();

        try {
            $category->delete();
            $this->fail('Expected the RESTRICT foreign key to refuse the delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // --- Deactivation ------------------------------------------------------

    public function test_a_category_with_active_products_cannot_be_deactivated(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $this->productIn($category, ['name' => 'Tumbler', 'is_active' => true]);
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        $this->assertTrue((bool) $category->fresh()->is_active);
        $this->assertSame(
            'This category still has 1 active product ("Tumbler"). Move them to another category before deactivating it.',
            $category->deactivationBlockedReason(),
        );
    }

    public function test_the_blocked_message_lists_at_most_three_products(): void
    {
        $category = Category::factory()->create();
        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            $this->productIn($category, ['name' => $name, 'is_active' => true]);
        }

        $this->assertStringContainsString(
            '5 active products ("A", "B" and "C" and 2 more)',
            $category->deactivationBlockedReason(),
        );
    }

    public function test_inactive_and_trashed_products_do_not_block_deactivation(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $this->productIn($category, ['is_active' => false]);
        $this->productIn($category, ['is_active' => true])->delete();
        $this->actingAsAdmin();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse((bool) $category->fresh()->is_active);
    }

    public function test_a_category_can_be_deactivated_once_its_products_are_moved(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = $this->productIn($category, ['is_active' => true]);
        $product->update(['category_id' => Category::factory()->create()->id]);

        $category->update(['is_active' => false]);

        $this->assertFalse((bool) $category->fresh()->is_active);
    }

    public function test_the_model_refuses_to_deactivate_a_category_with_active_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $this->productIn($category, ['is_active' => true]);

        try {
            $category->update(['is_active' => false]);
            $this->fail('Deactivating should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
        }

        $this->assertTrue((bool) $category->fresh()->is_active);
    }

    public function test_other_edits_to_a_category_with_active_products_still_save(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $this->productIn($category, ['is_active' => true]);

        $category->update(['sort_order' => 9]);

        $this->assertSame(9, (int) $category->fresh()->sort_order);
    }
}
