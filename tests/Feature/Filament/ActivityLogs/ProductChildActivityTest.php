<?php

namespace Tests\Feature\Filament\ActivityLogs;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\ActivityLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Product images and variants live in their own tables, so edits to them
 * never dirty a `products` column and the Product log alone can't see them.
 */
class ProductChildActivityTest extends TestCase
{
    use RefreshDatabase;

    private function lastActivityFor(object $model): Activity
    {
        return Activity::query()
            ->where('subject_type', $model::class)
            ->where('subject_id', $model->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    public function test_image_upload_reorder_and_removal_are_logged_with_the_product_name(): void
    {
        $admin = ActivityLogResourceTest::makeAdmin();
        $this->actingAs($admin);

        $product = Product::factory()->create(['name' => 'CLSU Hoodie']);

        // The same call the product form's gallery saver makes.
        $image = $product->generalImages()->updateOrCreate(
            ['image_path' => 'products/hoodie-front.jpg'],
            ['is_primary' => true, 'sort_order' => 0],
        );

        $created = $this->lastActivityFor($image);
        $this->assertSame('created', $created->event);
        $this->assertSame($admin->id, $created->causer_id);
        $this->assertSame('products/hoodie-front.jpg', $created->properties['attributes']['image_path']);
        $this->assertSame('CLSU Hoodie', $created->properties['product_name']);

        $image->update(['sort_order' => 3, 'is_primary' => false]);

        $updated = $this->lastActivityFor($image);
        $this->assertSame('updated', $updated->event);
        $this->assertSame(['is_primary' => true, 'sort_order' => 0], $updated->properties['old']);
        $this->assertSame(['is_primary' => false, 'sort_order' => 3], $updated->properties['attributes']);

        $image->delete();

        $deleted = $this->lastActivityFor($image);
        $this->assertSame('deleted', $deleted->event);
        $this->assertSame('products/hoodie-front.jpg', ActivityLogPresenter::for($deleted)->subjectTitle());
    }

    public function test_variant_price_and_stock_edits_are_logged(): void
    {
        $this->actingAs(ActivityLogResourceTest::makeAdmin());

        $product = Product::factory()->create(['name' => 'Varsity Shirt', 'has_variants' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'name' => 'Green - Large',
            'price' => 350,
            'stock_quantity' => 20,
        ]);

        $variant->update(['price' => 399, 'stock_quantity' => 15]);

        $updated = $this->lastActivityFor($variant);
        $this->assertSame('updated', $updated->event);
        $this->assertSame('Varsity Shirt', $updated->properties['product_name']);
        $this->assertEquals(350, $updated->properties['old']['price']);
        $this->assertEquals(399, $updated->properties['attributes']['price']);
        $this->assertSame(20, $updated->properties['old']['stock_quantity']);
        $this->assertSame(15, $updated->properties['attributes']['stock_quantity']);

        $variant->delete();

        $this->assertSame('deleted', $this->lastActivityFor($variant)->event);
    }

    public function test_changes_nobody_signed_in_as_admin_made_are_not_logged(): void
    {
        $admin = ActivityLogResourceTest::makeAdmin();
        $product = Product::factory()->create(['name' => 'Sticker', 'stock_quantity' => 116]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 25]);
        $before = Activity::count();

        // A customer's checkout decrements stock with no admin in the session.
        $product->update(['stock_quantity' => 103]);
        $variant->update(['stock_quantity' => 24]);

        $this->assertSame($before, Activity::count());

        // The same edit by an admin is the audit trail's business.
        $this->actingAs($admin);
        $product->update(['stock_quantity' => 90]);

        $this->assertSame($before + 1, Activity::count());
        $this->assertSame($admin->id, $this->lastActivityFor($product)->causer_id);
    }

    public function test_saving_an_unchanged_image_writes_no_row(): void
    {
        $image = ProductImage::factory()->create();
        $before = Activity::count();

        // The gallery saver re-runs updateOrCreate for every surviving image.
        $image->product->generalImages()->updateOrCreate(
            ['image_path' => $image->image_path],
            ['is_primary' => $image->is_primary, 'sort_order' => $image->sort_order],
        );

        $this->assertSame($before, Activity::count());
    }
}
