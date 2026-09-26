<?php

namespace Tests\Feature;

use App\Livewire\ProductDetails;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product page lists approved reviews one bounded page at a time, on its
 * own page name, newest first. It used to load every approved review and its
 * customer on mount and again on every Livewire request.
 */
class ProductDetailsReviewPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Asia/Manila'));

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'is_active' => true,
            'has_variants' => false,
            'price' => 100,
            'stock_quantity' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * $count approved reviews, one minute apart, so "Review 1" is the oldest
     * and "Review {$count}" the newest.
     */
    private function reviews(int $count, bool $approved = true, string $label = 'Review'): void
    {
        $customers = Customer::factory()->count(min($count, 20))->create();

        for ($i = 1; $i <= $count; $i++) {
            Review::create([
                'product_id' => $this->product->id,
                'customer_id' => $customers[$i % $customers->count()]->id,
                'rating' => 4,
                'title' => "$label $i",
                'comment' => "Comment for $label $i, long enough.",
                'is_approved' => $approved,
                'created_at' => now()->subDays(30)->addMinutes($i),
            ]);
        }
    }

    /** @return list<string> titles on the current page */
    private function shownTitles($component): array
    {
        return $component->viewData('reviews')->pluck('title')->all();
    }

    private function countRetrievedReviews(callable $callback): int
    {
        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Review::class, function () use (&$retrieved) {
            $retrieved++;
        });

        $callback();

        return $retrieved;
    }

    public function test_only_one_page_of_reviews_is_loaded_and_rendered(): void
    {
        $this->reviews(60);

        $component = null;
        $retrieved = $this->countRetrievedReviews(function () use (&$component) {
            $component = Livewire::test(ProductDetails::class, ['slug' => $this->product->slug]);
        });

        $this->assertSame(ProductDetails::REVIEWS_PER_PAGE, $retrieved);
        $this->assertCount(ProductDetails::REVIEWS_PER_PAGE, $component->viewData('reviews'));
        $this->assertSame(60, $component->viewData('reviews')->total());

        // A later request on the page (here: opening and closing a report
        // form) is bounded the same way -- hydrate no longer reloads them all.
        $roundTrip = $this->countRetrievedReviews(fn () => $component->call('cancelReport'));
        $this->assertSame(ProductDetails::REVIEWS_PER_PAGE, $roundTrip);
    }

    public function test_reviews_are_paged_newest_first_without_overlap_or_gaps(): void
    {
        $this->reviews(25);

        $component = Livewire::test(ProductDetails::class, ['slug' => $this->product->slug]);
        $seen = $this->shownTitles($component);

        $component->call('gotoPage', 2, ProductDetails::REVIEWS_PAGE_NAME);
        $seen = [...$seen, ...$this->shownTitles($component)];

        $component->call('gotoPage', 3, ProductDetails::REVIEWS_PAGE_NAME);
        $seen = [...$seen, ...$this->shownTitles($component)];

        $expected = array_map(fn (int $i) => "Review $i", range(25, 1));
        $this->assertSame($expected, $seen);
    }

    public function test_reviews_use_their_own_page_name(): void
    {
        $this->reviews(15);

        $component = Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->call('gotoPage', 2, ProductDetails::REVIEWS_PAGE_NAME);

        $this->assertSame(2, $component->viewData('reviews')->currentPage());
        $this->assertSame(ProductDetails::REVIEWS_PAGE_NAME, $component->viewData('reviews')->getPageName());
        $this->assertSame(['reviewsPage' => 2], $component->get('paginators'));
    }

    public function test_the_summary_counts_every_approved_review_not_just_the_page(): void
    {
        $this->reviews(23);
        $this->reviews(4, approved: false, label: 'Hidden');

        Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->assertSeeText('23 reviews')
            ->assertDontSeeText('Hidden 1');
    }

    public function test_an_unapproved_review_disappears_from_the_list(): void
    {
        $this->reviews(3);
        $review = Review::where('title', 'Review 3')->first();

        $component = Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->assertSeeText('Review 3');

        // An admin un-approves it (moderation happens in the panel).
        $review->update(['is_approved' => false]);

        $component->call('cancelReport')
            ->assertDontSeeText('Review 3')
            ->assertSeeText('2 reviews');
    }

    public function test_a_submitted_review_appears_immediately_on_the_first_page(): void
    {
        $this->reviews(15);

        $customer = Customer::factory()->create(['first_name' => 'Newest', 'last_name' => 'Reviewer']);
        $order = Order::create([
            'customer_id' => $customer->id,
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

        $this->actingAs($customer, 'customer');

        $component = Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->call('gotoPage', 2, ProductDetails::REVIEWS_PAGE_NAME)
            ->set('reviewTitle', 'Just posted')
            ->set('reviewComment', 'Posted a moment ago, and it should be first.')
            ->call('submitReview')
            ->assertHasNoErrors();

        $this->assertSame(1, $component->viewData('reviews')->currentPage());
        $this->assertSame('Just posted', $this->shownTitles($component)[0]);
        $component->assertSeeText('16 reviews');
    }

    public function test_a_review_on_a_later_page_can_still_be_reported(): void
    {
        $this->reviews(15);
        $oldest = Review::where('title', 'Review 1')->first();

        $this->actingAs(Customer::factory()->create(), 'customer');

        Livewire::test(ProductDetails::class, ['slug' => $this->product->slug])
            ->call('gotoPage', 2, ProductDetails::REVIEWS_PAGE_NAME)
            ->assertSeeText('Review 1')
            ->call('startReport', $oldest->id)
            ->set('reportReason', 'Spam or advertising')
            ->call('submitReport')
            ->assertHasNoErrors()
            ->assertSeeText('Reported to admin');

        $this->assertTrue(Report::where('review_id', $oldest->id)->exists());
    }
}
