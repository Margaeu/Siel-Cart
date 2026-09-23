<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDisplayPriceLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_product_displays_its_price(): void
    {
        $product = Product::factory()->create([
            'has_variants' => false,
            'price' => 899,
        ]);

        $this->assertSame('₱899.00', $product->display_price_label);
    }

    public function test_variable_product_displays_one_price_when_active_variant_prices_match(): void
    {
        $product = Product::factory()->create([
            'has_variants' => true,
            'price' => null,
        ]);

        ProductVariant::factory()->count(2)->for($product)->create(['price' => 899]);

        $this->assertSame('₱899.00', $product->display_price_label);
    }

    public function test_variable_product_displays_a_range_when_active_variant_prices_differ(): void
    {
        $product = Product::factory()->create([
            'has_variants' => true,
            'price' => null,
        ]);

        ProductVariant::factory()->for($product)->create(['price' => 899]);
        ProductVariant::factory()->for($product)->create(['price' => 999]);

        $this->assertSame('₱899.00–₱999.00', $product->display_price_label);

        $product->load('variants');

        $this->assertSame('₱899.00–₱999.00', $product->display_price_label);
    }

    public function test_variable_product_price_range_ignores_inactive_variants(): void
    {
        $product = Product::factory()->create([
            'has_variants' => true,
            'price' => null,
        ]);

        ProductVariant::factory()->for($product)->create(['price' => 899]);
        ProductVariant::factory()->for($product)->create([
            'price' => 999,
            'is_active' => false,
        ]);

        $this->assertSame('₱899.00', $product->display_price_label);
    }
}
