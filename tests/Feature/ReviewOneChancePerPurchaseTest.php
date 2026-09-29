<?php

namespace Tests\Feature;

use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Livewire\Customer\OrderDetails;
use App\Livewire\ProductDetails;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A customer gets one review per completed order for a product. Moderation
 * (hiding or deleting a reported review) must not hand that chance back:
 * deleting a review used to remove the only record that the order had been
 * reviewed, so its author could immediately post a new one.
 */
class ReviewOneChancePerPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function reviewFor(Order $order): Review
    {
        return Review::create([
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'rating' => 1,
            'comment' => 'The review that gets reported.',
            'is_verified_purchase' => true,
            'is_approved' => true,
        ]);
    }

    private function productPage()
    {
        $this->actingAs($this->customer, 'customer');

        return Livewire::test(ProductDetails::class, ['slug' => $this->product->slug]);
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    private function reportFor(Review $review): Report
    {
        return Report::create([
            'reporter_customer_id' => Customer::factory()->create()->id,
            'reported_customer_id' => $this->customer->id,
            'review_id' => $review->id,
            'reason' => 'Fake or misleading review',
            'status' => 'pending',
        ]);
    }

    public function test_reports_can_hide_a_review_without_deleting_it_or_allowing_another_review(): void
    {
        $this->actingAsAdmin();

        foreach ([EditReport::class, ViewReport::class] as $page) {
            $review = $this->reviewFor($this->completedOrder());
            $report = $this->reportFor($review);

            Livewire::test($page, ['record' => $report->id])
                ->assertActionVisible('hideReview')
                ->callAction('hideReview')
                ->assertNotified('Review hidden')
                ->assertRedirect(ReportResource::getUrl('index'));

            $this->assertFalse($review->fresh()->is_approved);
            $this->assertFalse($review->fresh()->trashed());
            $this->assertSame('reviewed', $report->fresh()->status);
            $this->assertSame($review->id, $report->fresh()->review_id);
            $this->assertSame(0, $this->product->fresh()->reviews_count);

            $this->productPage()
                ->assertDontSeeText('The review that gets reported.')
                ->assertSet('canReview', false)
                ->assertSet('hasReview', true);
        }
    }

    public function test_report_hide_action_is_unavailable_for_hidden_or_deleted_reviews(): void
    {
        $this->actingAsAdmin();
        $review = $this->reviewFor($this->completedOrder());
        $report = $this->reportFor($review);
        $review->update(['is_approved' => false]);

        foreach ([EditReport::class, ViewReport::class] as $page) {
            Livewire::test($page, ['record' => $report->id])
                ->assertActionHidden('hideReview');
        }

        $review->delete();

        foreach ([EditReport::class, ViewReport::class] as $page) {
            Livewire::test($page, ['record' => $report->id])
                ->assertActionHidden('hideReview');
        }

        $this->assertSame('pending', $report->fresh()->status);
    }

    public function test_a_deleted_review_does_not_let_the_customer_review_again(): void
    {
        $order = $this->completedOrder();
        $this->reviewFor($order)->delete();

        $this->productPage()
            ->assertSet('canReview', false)
            ->assertSet('hasReview', true)
            ->set('reviewComment', 'Trying to post a second review.')
            ->call('submitReview')
            ->assertHasErrors('review');

        $this->assertSame(0, Review::count());
        $this->assertSame(1, Review::withTrashed()->count());
    }

    public function test_a_hidden_review_does_not_let_the_customer_review_again(): void
    {
        $order = $this->completedOrder();
        $this->reviewFor($order)->update(['is_approved' => false]);

        $this->productPage()
            ->assertSet('canReview', false)
            ->assertSet('hasReview', true)
            ->set('reviewComment', 'Trying to post a second review.')
            ->call('submitReview')
            ->assertHasErrors('review');

        $this->assertSame(1, Review::withTrashed()->count());
    }

    public function test_a_deleted_review_is_gone_from_the_product_page(): void
    {
        $this->reviewFor($this->completedOrder())->delete();

        $this->productPage()->assertDontSeeText('The review that gets reported.');

        $this->assertSame(0, $this->product->fresh()->reviews_count);
    }

    public function test_order_details_shows_reviewed_instead_of_a_write_link_after_deletion(): void
    {
        $order = $this->completedOrder();
        $this->reviewFor($order)->delete();

        $this->actingAs($this->customer, 'customer');

        Livewire::test(OrderDetails::class, ['id' => $order->id])
            ->assertSet('reviewedProductIds', [$this->product->id])
            ->assertDontSeeText('Write a Review');
    }

    public function test_a_later_purchase_is_still_its_own_chance_to_review(): void
    {
        $this->reviewFor($this->completedOrder())->delete();
        $repeat = $this->completedOrder();

        $this->productPage()
            ->assertSet('canReview', true)
            ->set('reviewComment', 'Reviewing my second purchase.')
            ->call('submitReview')
            ->assertHasNoErrors();

        $this->assertTrue(Review::where('order_id', $repeat->id)->exists());
    }

    public function test_customer_without_completed_purchase_sees_eligibility_explanation(): void
    {
        $this->productPage()
            ->assertSeeText('Only customers with a completed order can review this product.')
            ->assertDontSeeText('Write a Review')
            ->set('reviewComment', 'A review without a completed purchase.')
            ->call('submitReview')->assertHasErrors('review');

        $this->assertSame(0, Review::count());
    }
}
