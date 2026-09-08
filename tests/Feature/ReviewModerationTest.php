<?php

namespace Tests\Feature;

use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        \Filament\Facades\Filament::setCurrentPanel('admin');
    }

    protected function makeReview(bool $approved = false): Review
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Smoke Test Shirt',
            'slug' => 'smoke-test-shirt-'.uniqid(),
            'sku' => 'SMOKE-'.uniqid(),
            'price' => 100,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        return Review::create([
            'product_id' => $product->id,
            'customer_id' => Customer::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->id,
            'rating' => 4,
            'title' => 'Solid shirt',
            'comment' => 'Good quality for the price.',
            'is_verified_purchase' => true,
            'is_approved' => $approved,
        ]);
    }

    public function test_list_page_renders_columns_and_pending_reviews(): void
    {
        $this->makeReview();

        Livewire::test(ListReviews::class)
            ->assertOk()
            ->assertSeeText('Smoke Test Shirt')
            ->assertSeeText('Juan Dela Cruz')
            ->assertSeeText('Solid shirt')
            ->assertSeeText('Pending');
    }

    public function test_approve_record_action_flips_the_flag(): void
    {
        $review = $this->makeReview();

        Livewire::test(ListReviews::class)
            ->assertTableActionVisible('approve', $review)
            ->assertTableActionHidden('unapprove', $review)
            ->callTableAction('approve', $review)
            ->assertHasNoActionErrors();

        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_bulk_approve_action_flips_the_flag(): void
    {
        $a = $this->makeReview();
        $b = $this->makeReview();

        Livewire::test(ListReviews::class)
            ->callTableBulkAction('approve', [$a, $b])
            ->assertHasNoActionErrors();

        $this->assertTrue($a->fresh()->is_approved);
        $this->assertTrue($b->fresh()->is_approved);
    }

    public function test_search_and_filters_do_not_error(): void
    {
        $pending = $this->makeReview(false);
        $approved = $this->makeReview(true);

        Livewire::test(ListReviews::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved])
            ->filterTable('is_approved', true)
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$pending])
            ->filterTable('rating', 4)
            ->searchTable('Juan')
            ->assertOk();
    }

    public function test_edit_form_renders_and_saves_only_the_approval_flag(): void
    {
        $review = $this->makeReview();

        Livewire::test(EditReview::class, ['record' => $review->getKey()])
            ->assertOk()
            ->assertSeeText('Solid shirt')
            ->assertSeeText('Good quality for the price.')
            ->assertSeeText('Juan Dela Cruz')
            ->assertFormSet(['is_approved' => false])
            ->fillForm(['is_approved' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $review->refresh();
        $this->assertTrue($review->is_approved);
        $this->assertSame('Solid shirt', $review->title);
        $this->assertSame(4, $review->rating);
    }
}
