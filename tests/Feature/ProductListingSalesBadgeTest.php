<?php

namespace Tests\Feature;

use App\Livewire\ProductListing;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListingSalesBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_products_page_reflects_best_seller_and_top_pick_rankings(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'name' => 'Ranked Campus Shirt',
            'is_active' => true,
            'is_featured' => true,
            'has_variants' => false,
            'price' => 350,
            'stock_quantity' => 10,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 700,
            'total' => 700,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => $product->price,
            'quantity' => 2,
            'subtotal' => 700,
        ]);

        Livewire::test(ProductListing::class)
            ->assertSeeText($product->name)
            ->assertSeeText('Best Seller')
            ->assertSeeText('Top Pick')
            ->assertDontSeeText('Featured');
    }

    public function test_products_page_does_not_add_sales_badges_without_a_qualifying_sale(): void
    {
        Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'name' => 'Unsold Campus Mug',
            'is_active' => true,
            'is_featured' => true,
            'has_variants' => false,
            'price' => 150,
            'stock_quantity' => 10,
        ]);

        Livewire::test(ProductListing::class)
            ->assertSeeText('Unsold Campus Mug')
            ->assertSeeText('Featured')
            ->assertDontSeeText('Best Seller')
            ->assertDontSeeText('Top Pick');
    }
}
