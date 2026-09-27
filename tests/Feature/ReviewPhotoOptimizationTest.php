<?php

namespace Tests\Feature;

use App\Livewire\ProductDetails;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Review photos used to reach R2 through a bare ->store(), so whatever came
 * off the customer's phone was stored at full resolution and served untouched
 * to every visitor who opened the product page -- up to five per review, ten
 * reviews to a page. They now go through OptimizedImageStorage like every
 * other image on the site.
 *
 * That also put them in front of GD for the first time, which is why the
 * pixel budget is asserted here too: file size does not bound a decode, so
 * without it a customer upload could exhaust the worker's memory.
 */
class ReviewPhotoOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'is_active' => true,
            'has_variants' => false,
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $this->customer = Customer::factory()->create();
    }

    private function completedOrder(): Order
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        return $order;
    }

    /**
     * A PNG header with no pixel data. getimagesize() only reads the header,
     * so this describes a 20 megapixel image in 33 bytes -- the point being
     * that the check never has to decode the file it is protecting against.
     */
    private function oversizedImage(int $width, int $height): File
    {
        $ihdr = 'IHDR'.pack('N', $width).pack('N', $height).chr(8).chr(2).chr(0).chr(0).chr(0);

        // Built through fake() rather than `new UploadedFile` because Livewire's
        // ->set() helper reads $file->name, which only the testing File carries.
        $file = UploadedFile::fake()->create('huge.png', 1, 'image/png');

        file_put_contents(
            $file->getRealPath(),
            "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr)),
        );

        return $file;
    }

    public function test_a_submitted_review_photo_is_stored_re_encoded_at_the_capped_width(): void
    {
        $this->completedOrder();

        Livewire::actingAs($this->customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $this->product->slug])
            ->set('reviewRating', 5)
            ->set('reviewComment', 'Good quality hoodie, fits as described.')
            ->set('reviewPhotos', [UploadedFile::fake()->image('snap.jpg', 3000, 2000)])
            ->call('submitReview')
            ->assertHasNoErrors();

        $stored = Storage::disk('r2')->files('reviews/photos');

        $this->assertCount(1, $stored);

        $image = ImageManager::gd()->read(Storage::disk('r2')->get($stored[0]));

        $this->assertSame(ProductDetails::REVIEW_PHOTO_MAX_WIDTH, $image->width());
        $this->assertLessThan(3000 * 2000, $image->width() * $image->height());
    }

    public function test_a_review_photo_over_the_pixel_budget_is_refused(): void
    {
        $this->completedOrder();

        Livewire::actingAs($this->customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $this->product->slug])
            ->set('reviewRating', 5)
            ->set('reviewComment', 'Good quality hoodie, fits as described.')
            ->set('reviewPhotos', [$this->oversizedImage(5000, 4000)])
            ->call('submitReview')
            ->assertHasErrors('reviewPhotos.0');

        $this->assertSame(0, Review::count(), 'An oversized photo must not leave a review behind.');
        $this->assertCount(0, Storage::disk('r2')->files('reviews/photos'));
    }

    public function test_review_photo_thumbnails_are_lazily_loaded(): void
    {
        Review::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $this->completedOrder()->id,
            'rating' => 5,
            'comment' => 'Good quality hoodie, fits as described.',
            'photos' => ['reviews/photos/example.jpg'],
            'is_verified_purchase' => true,
            'is_approved' => true,
        ]);

        // A page of reviews can carry fifty of these below the fold.
        Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false);
    }
}
