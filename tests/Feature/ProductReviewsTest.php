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
use Livewire\Livewire;
use Tests\TestCase;

class ProductReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(): Product
    {
        return Product::create([
            'category_id' => Category::factory()->create()->id,
            'name' => 'CLSU Review Test Shirt',
            'slug' => 'clsu-review-test-shirt',
            'sku' => 'REVIEW-TEST-001',
            'description' => '<p>A product used to verify customer reviews.</p>',
            'price' => 350,
            'stock_quantity' => 10,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);
    }

    public function test_product_page_displays_only_approved_reviews_with_the_correct_rating_and_count(): void
    {
        $product = $this->makeProduct();

        Review::create([
            'product_id' => $product->id,
            'customer_id' => Customer::factory()->create([
                'first_name' => 'Approved',
                'last_name' => 'Five Star',
            ])->id,
            'rating' => 5,
            'title' => 'Excellent approved review',
            'comment' => 'This approved review should be visible.',
            'is_approved' => true,
        ]);

        Review::create([
            'product_id' => $product->id,
            'customer_id' => Customer::factory()->create([
                'first_name' => 'Approved',
                'last_name' => 'Three Star',
            ])->id,
            'rating' => 3,
            'title' => 'Useful approved review',
            'comment' => 'This second approved review should be visible.',
            'is_approved' => true,
        ]);

        Review::create([
            'product_id' => $product->id,
            'customer_id' => Customer::factory()->create([
                'first_name' => 'Pending',
                'last_name' => 'Reviewer',
            ])->id,
            'rating' => 1,
            'title' => 'Pending review must stay hidden',
            'comment' => 'This pending review must not affect the public score.',
            'is_approved' => false,
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSeeText('4.0 (2 reviews)')
            ->assertSeeText('Reviews (2)')
            ->assertSeeText('Excellent approved review')
            ->assertSeeText('Useful approved review')
            ->assertDontSeeText('Pending review must stay hidden')
            ->assertDontSeeText('Pending Reviewer');
    }

    public function test_customer_can_submit_a_review_for_a_product_from_a_completed_order(): void
    {
        $product = $this->makeProduct();
        $customer = Customer::factory()->create();
        $order = Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 350,
            'total' => 350,
            'pickup_location' => 'UBAP Office',
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 350,
            'quantity' => 1,
            'subtotal' => 350,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductDetails::class, ['slug' => $product->slug])
            ->assertSeeText('Write a Review')
            ->set('reviewRating', 5)
            ->set('reviewTitle', 'A great campus shirt')
            ->set('reviewComment', 'The shirt is comfortable and the print looks great.')
            ->call('submitReview')
            ->assertHasNoErrors()
            ->assertSeeText('Your review has been submitted and is awaiting approval.');

        $this->actingAs($customer, 'customer')
            ->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSeeText('Your review has been submitted and is awaiting approval.')
            ->assertDontSeeText('A great campus shirt');
    }
}
