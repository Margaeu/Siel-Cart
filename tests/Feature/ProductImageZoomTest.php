<?php

namespace Tests\Feature;

use App\Livewire\ProductDetails;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImageZoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_photo_opens_an_accessible_zoom_viewer(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'has_variants' => false,
            'price' => 350,
            'stock_quantity' => 10,
        ]);

        ProductImage::factory()->for($product)->create([
            'image_path' => 'products/tumbler.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        Livewire::test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSeeHtml('aria-label="Open product image viewer"')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-label="Product image viewer"')
            ->assertSeeHtml('aria-label="Zoom out"')
            ->assertSeeHtml('aria-label="Zoom in"')
            ->assertSeeText('Click the image to zoom. Drag while zoomed.');
    }
}
