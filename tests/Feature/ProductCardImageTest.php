<?php

namespace Tests\Feature;

use App\Livewire\ProductCard;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCardImageTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'is_active' => true,
            'has_variants' => true,
        ]);
    }

    public function test_card_uses_an_active_variant_image_when_there_is_no_primary_image(): void
    {
        $product = $this->product();
        $variant = ProductVariant::factory()->for($product)->create([
            'is_active' => true,
        ]);

        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $variant->id,
            'image_path' => 'products/variant-fallback.jpg',
        ]);

        Livewire::test(ProductCard::class, ['product' => $product])
            ->assertOk()
            ->assertSee('products/variant-fallback.jpg');
    }

    public function test_card_keeps_the_primary_image_ahead_of_variant_images(): void
    {
        $product = $this->product();
        $variant = ProductVariant::factory()->for($product)->create([
            'is_active' => true,
        ]);

        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $variant->id,
            'image_path' => 'products/variant-fallback.jpg',
            'sort_order' => 0,
        ]);
        ProductImage::factory()->primary()->for($product)->create([
            'image_path' => 'products/shared-primary.jpg',
            'sort_order' => 10,
        ]);

        Livewire::test(ProductCard::class, ['product' => $product])
            ->assertOk()
            ->assertSee('products/shared-primary.jpg')
            ->assertDontSee('products/variant-fallback.jpg');
    }

    public function test_card_does_not_use_images_from_inactive_variants(): void
    {
        $product = $this->product();
        $inactiveVariant = ProductVariant::factory()->for($product)->create([
            'is_active' => false,
        ]);
        $activeVariant = ProductVariant::factory()->for($product)->create([
            'is_active' => true,
        ]);

        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $inactiveVariant->id,
            'image_path' => 'products/inactive-variant.jpg',
            'sort_order' => 0,
        ]);
        ProductImage::factory()->for($product)->create([
            'product_variant_id' => $activeVariant->id,
            'image_path' => 'products/active-variant.jpg',
            'sort_order' => 1,
        ]);

        Livewire::test(ProductCard::class, ['product' => $product])
            ->assertOk()
            ->assertSee('products/active-variant.jpg')
            ->assertDontSee('products/inactive-variant.jpg');
    }
}
