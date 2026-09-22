<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReportsFiledRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReportsReceivedRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Account deletion is permanent: identity and credentials are erased, the row
 * is soft-deleted (never force-deleted, since orders/reviews/reports cascade on
 * it), and the retained history is attributed to "Deleted customer".
 */
class CustomerAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'maria@example.com';

    private const PASSWORD = 'secret-password';

    private function customer(): Customer
    {
        return Customer::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'phone' => '09171234567',
            'date_of_birth' => '2000-01-15',
        ]);
    }

    private function order(Customer $customer, string $status = 'completed'): Order
    {
        return Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 350,
            'total' => 350,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => $status === 'completed' ? 'paid' : 'pending',
            'status' => $status,
        ]);
    }

    /** Review has no HasFactory, so reviews are built directly. */
    private function review(Customer $customer, Product $product, array $attributes = []): Review
    {
        return Review::create(array_merge([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Nice',
            'comment' => 'Good quality.',
            'is_approved' => true,
        ], $attributes));
    }

    private function deleteViaProfile(Customer $customer)
    {
        return $this->actingAs($customer, 'customer')
            ->from(route('customer.profile'))
            ->post(route('account.delete'));
    }

    // --- Active orders -------------------------------------------------------

    public static function activeStatuses(): array
    {
        return [['pending'], ['processing'], ['ready_for_pickup']];
    }

    #[DataProvider('activeStatuses')]
    public function test_deletion_is_rejected_while_an_order_is_active(string $status): void
    {
        $customer = $this->customer();
        $this->order($customer, $status);

        $this->deleteViaProfile($customer)
            ->assertRedirect(route('customer.profile'))
            ->assertSessionHas('error');

        $customer->refresh();
        $this->assertFalse($customer->trashed());
        $this->assertSame(self::EMAIL, $customer->email);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_completed_and_cancelled_orders_do_not_block_deletion(): void
    {
        $customer = $this->customer();
        $this->order($customer, 'completed');
        $this->order($customer, 'cancelled');

        $this->deleteViaProfile($customer)->assertRedirect(route('login'));

        $this->assertSoftDeleted($customer);
    }

    // --- What deletion does --------------------------------------------------

    public function test_deletion_anonymizes_soft_deletes_and_logs_out(): void
    {
        $customer = $this->customer();
        $oldHash = $customer->password;
        $createdAt = $customer->created_at->toDateTimeString();

        $this->deleteViaProfile($customer)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest('customer');

        $row = Customer::withTrashed()->findOrFail($customer->id);

        $this->assertNotNull($row->deleted_at);
        $this->assertNotSame('Maria', $row->first_name);
        $this->assertNotSame('Santos', $row->last_name);
        $this->assertNotSame(self::EMAIL, $row->email);
        $this->assertStringNotContainsString('maria', strtolower($row->email));
        $this->assertMatchesRegularExpression(
            '/^deleted-'.$customer->id.'-[a-z0-9]{32}@deleted\.invalid$/',
            $row->email,
        );
        $this->assertSame($createdAt, $row->created_at->toDateTimeString());
        $this->assertNull($row->phone);
        $this->assertNull($row->date_of_birth);
        $this->assertNull($row->remember_token);
        $this->assertNull($row->email_verified_at);
        $this->assertFalse($row->is_active);
        $this->assertNotSame($oldHash, $row->password);
        $this->assertFalse(Hash::check(self::PASSWORD, $row->password));

        // Nothing that looks like the label ends up stored in a column.
        $this->assertNotContains(Customer::DELETED_LABEL, $row->getAttributes());
    }

    public function test_anonymized_emails_are_unique_per_deleted_customer(): void
    {
        $first = $this->customer();
        $second = Customer::factory()->create();

        $first->deleteAccount();
        $second->deleteAccount();

        $emails = Customer::onlyTrashed()->pluck('email');

        $this->assertCount(2, $emails->unique());
    }

    public function test_deletion_removes_the_cart_and_its_items(): void
    {
        $customer = $this->customer();
        $cart = Cart::create(['customer_id' => $customer->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 2,
        ]);

        $customer->deleteAccount();

        $this->assertModelMissing($cart);
        $this->assertModelMissing($item);
    }

    public function test_deletion_removes_password_reset_tokens_for_the_old_email(): void
    {
        $customer = $this->customer();
        DB::table('password_reset_tokens')->insert([
            'email' => self::EMAIL,
            'token' => Hash::make('token'),
            'created_at' => now(),
        ]);

        $customer->deleteAccount();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
    }

    public function test_the_row_is_never_force_deleted(): void
    {
        $customer = $this->customer();

        $customer->deleteAccount();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_repeating_the_deletion_changes_nothing(): void
    {
        $customer = $this->customer();
        // A second request (double submit, admin and customer at once) holds
        // an instance loaded before the first deletion committed.
        $stale = Customer::findOrFail($customer->id);

        $customer->deleteAccount();
        $afterFirst = Customer::withTrashed()->findOrFail($customer->id)->getAttributes();

        $stale->deleteAccount();
        $customer->deleteAccount();

        $this->assertSame($afterFirst, Customer::withTrashed()->findOrFail($customer->id)->getAttributes());
        // The stale caller now sees the erased values, not the originals.
        $this->assertTrue($stale->trashed());
        $this->assertNotSame(self::EMAIL, $stale->email);
    }

    public function test_an_existing_login_session_stops_working_after_deletion(): void
    {
        $customer = $this->customer();
        $customer->markEmailAsVerified();

        $this->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($customer, 'customer');

        // Deleted from another device while this session is still open.
        Customer::findOrFail($customer->id)->deleteAccount();
        Auth::forgetGuards();

        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('customer');
    }

    public function test_an_old_verification_link_cannot_verify_the_deleted_account(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['email_verified_at' => null])->save();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $customer->id,
            'hash' => sha1(self::EMAIL),
        ]);

        $customer->deleteAccount();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertNull(Customer::withTrashed()->findOrFail($customer->id)->email_verified_at);
    }

    // --- Retained history ----------------------------------------------------

    public function test_orders_reviews_and_reports_remain_attached_to_the_deleted_customer(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create();
        $product = Product::factory()->create();

        $order = $this->order($customer);
        $line = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 350,
            'quantity' => 1,
            'subtotal' => 350,
        ]);
        $history = OrderStatusHistory::where('order_id', $order->id)->firstOrFail();

        $review = $this->review($customer, $product, [
            'order_id' => $order->id,
            'photos' => ['reviews/photos/a.jpg', 'reviews/photos/b.jpg'],
            'video_path' => 'reviews/videos/clip.mp4',
        ]);
        $otherReview = $this->review($other, $product);

        $filed = Report::create([
            'reporter_customer_id' => $customer->id,
            'reported_customer_id' => $other->id,
            'review_id' => $otherReview->id,
            'reason' => 'Spam',
            'status' => 'pending',
        ]);
        $received = Report::create([
            'reporter_customer_id' => $other->id,
            'reported_customer_id' => $customer->id,
            'review_id' => $review->id,
            'reason' => 'Offensive',
            'status' => 'pending',
        ]);

        $customer->deleteAccount();

        $this->assertModelExists($order);
        $this->assertModelExists($line);
        $this->assertModelExists($history);
        $this->assertModelExists($filed);
        $this->assertModelExists($received);

        $review->refresh();
        $this->assertSame($customer->id, $review->customer_id);
        $this->assertSame(['reviews/photos/a.jpg', 'reviews/photos/b.jpg'], $review->photos);
        $this->assertSame('reviews/videos/clip.mp4', $review->video_path);

        // Retained relationships still load the soft-deleted customer.
        $this->assertTrue($order->fresh()->customer->is($customer));
        $this->assertTrue($review->customer->is($customer));
        $this->assertTrue($filed->fresh()->reporter->is($customer));
        $this->assertTrue($received->fresh()->reportedCustomer->is($customer));

        $this->assertSame(Customer::DELETED_LABEL, $review->customer->name);
        $this->assertSame(Customer::DELETED_LABEL, $order->fresh()->customer->name);
    }

    public function test_the_public_product_page_attributes_the_review_to_deleted_user(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['is_active' => true]);
        $this->review($customer, $product, ['comment' => 'Great shirt, fits well.']);

        $customer->deleteAccount();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Great shirt, fits well.')
            ->assertSee(Customer::DELETED_LABEL)
            ->assertDontSee('Maria Santos');
    }

    // --- Access after deletion -----------------------------------------------

    public function test_the_old_credentials_no_longer_log_in(): void
    {
        $customer = $this->customer();
        $customer->deleteAccount();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest('customer');
        $this->assertSoftDeleted($customer);
    }

    public function test_remember_token_and_session_lookups_cannot_restore_access(): void
    {
        $customer = $this->customer();
        $oldToken = $customer->remember_token;
        $customer->deleteAccount();

        $provider = Auth::guard('customer')->getProvider();

        $this->assertNull($provider->retrieveByToken($customer->id, $oldToken));
        $this->assertNull($provider->retrieveById($customer->id));
        $this->assertSoftDeleted($customer);
    }

    public function test_password_reset_cannot_target_the_deleted_account(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $customer->deleteAccount();

        $this->post(route('password.email'), ['email' => self::EMAIL]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => self::EMAIL]);
    }

    public function test_the_original_email_can_register_again(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $customer->deleteAccount();
        $this->flushSession();

        $this->post(route('register.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'email' => self::EMAIL,
            'date_of_birth' => '1999-05-05',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $fresh = Customer::where('email', self::EMAIL)->firstOrFail();
        $this->assertNotSame($customer->id, $fresh->id);
        $this->assertSoftDeleted($customer);
    }

    public function test_a_deactivated_account_is_refused_rather_than_reactivated(): void
    {
        $customer = $this->customer();
        $customer->update(['is_active' => false]);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest('customer');
        $this->assertFalse($customer->fresh()->is_active);
    }

    public function test_the_deactivate_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('account.deactivate'));

        $this->actingAs($this->customer(), 'customer')
            ->post('/account/deactivate')
            ->assertNotFound();
    }

    /** The fifth failure shows the lockout message straight away. */
    public function test_login_rate_limiting_still_locks_out_on_the_fifth_failure(): void
    {
        $this->customer();

        $messages = [];
        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))
                ->post(route('login.store'), ['email' => self::EMAIL, 'password' => 'wrong']);
            $messages[] = session('errors')?->first('email');
            $this->flushSession();
        }

        $this->assertSame(array_fill(0, 4, __('auth.failed')), array_slice($messages, 0, 4));
        $this->assertStringStartsWith('Too many failed login attempts.', $messages[4]);
        $this->assertGuest('customer');
    }

    // --- Admin panel ---------------------------------------------------------

    private function actingAsAdmin(): void
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        // User::canAccessPanel() needs one of the panel roles.
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        // Authorization is not what these tests are about.
        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    public function test_admin_can_inspect_a_deleted_customer_and_their_history(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = $this->order($customer);
        $review = $this->review($customer, $product);
        $otherReview = $this->review($other, $product);
        $filed = Report::create([
            'reporter_customer_id' => $customer->id,
            'reported_customer_id' => $other->id,
            'review_id' => $otherReview->id,
            'reason' => 'Spam',
            'status' => 'pending',
        ]);
        $received = Report::create([
            'reporter_customer_id' => $other->id,
            'reported_customer_id' => $customer->id,
            'review_id' => $review->id,
            'reason' => 'Offensive',
            'status' => 'pending',
        ]);
        $customer->deleteAccount();
        $this->actingAsAdmin();

        $owner = Customer::withTrashed()->find($customer->id);
        $managers = [
            ReviewsRelationManager::class => $review,
            ReportsFiledRelationManager::class => $filed,
            ReportsReceivedRelationManager::class => $received,
        ];
        foreach ($managers as $manager => $record) {
            Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => ViewCustomer::class])
                ->assertCanSeeTableRecords([$record]);
        }

        $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
            ->assertOk()
            ->assertSee('Deleted customer details')
            ->assertSee('Permanently deleted')
            ->assertSee(Customer::DELETED_LABEL)
            ->assertSee(Customer::REMOVED_LABEL)
            ->assertSee('Customer ID')
            ->assertSee('Account deleted')
            ->assertDontSee(self::EMAIL)
            ->assertDontSee('09171234567')
            ->assertDontSee('deleted.invalid')
            ->assertDontSee('Deleted User')
            ->assertDontSee('[Deleted');

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertSuccessful()
            ->assertActionDoesNotExist('deleteAccount')
            ->assertActionHidden('edit');

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => Customer::withTrashed()->find($customer->id),
            'pageClass' => ViewCustomer::class,
        ])->assertCanSeeTableRecords([$order]);
    }

    public function test_a_deleted_customer_cannot_be_edited(): void
    {
        $customer = $this->customer();
        $customer->deleteAccount();
        $this->actingAsAdmin();

        $this->get(CustomerResource::getUrl('edit', ['record' => $customer]))
            ->assertForbidden();
    }

    public function test_customer_list_tabs_separate_active_inactive_and_deleted(): void
    {
        $deleted = $this->customer();
        $deleted->deleteAccount();
        $active = Customer::factory()->create(['is_active' => true]);
        $inactive = Customer::factory()->create(['is_active' => false]);
        $this->actingAsAdmin();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$active, $inactive, $deleted])
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive, $deleted])
            ->set('activeTab', 'inactive')
            ->assertCanSeeTableRecords([$inactive])
            // A deleted account is also inactive; it belongs only under Deleted.
            ->assertCanNotSeeTableRecords([$active, $deleted])
            ->set('activeTab', 'deleted')
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active, $inactive]);
    }

    public function test_no_generic_delete_force_delete_or_restore_is_offered(): void
    {
        $customer = $this->customer();
        $this->actingAsAdmin();

        $this->assertFalse(CustomerResource::canDelete($customer));
        $this->assertFalse(CustomerResource::canForceDelete($customer));
        $this->assertFalse(CustomerResource::canForceDeleteAny());
        $this->assertFalse(CustomerResource::canRestore($customer));

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertActionDoesNotExist('delete')
            ->assertActionDoesNotExist('forceDelete')
            ->assertActionDoesNotExist('restore');
    }

    /** Only the customer deletes their own account; the panel cannot. */
    public function test_admins_are_offered_no_way_to_delete_a_customer(): void
    {
        $customer = $this->customer();
        $this->actingAsAdmin();

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertActionDoesNotExist('deleteAccount')
            ->assertActionDoesNotExist('delete');

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertActionDoesNotExist('deleteAccount');

        Livewire::test(ListCustomers::class)
            ->assertTableActionDoesNotExist('deleteAccount')
            ->assertTableActionDoesNotExist('delete');

        $this->assertFalse($customer->fresh()->trashed());
    }

    public function test_admin_order_page_shows_deleted_user_for_a_deleted_customer(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $customer->deleteAccount();
        $this->actingAsAdmin();

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee(Customer::DELETED_LABEL)
            ->assertDontSee(self::EMAIL)
            ->assertDontSee('deleted.invalid');
    }

    public function test_admin_review_and_report_pages_show_deleted_customer(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create();
        $product = Product::factory()->create();
        // Pending, because the reviews list opens on its "Pending only" filter.
        $review = $this->review($customer, $product, ['is_approved' => false]);
        Report::create([
            'reporter_customer_id' => $other->id,
            'reported_customer_id' => $customer->id,
            'review_id' => $review->id,
            'reason' => 'Offensive',
            'status' => 'pending',
        ]);
        $customer->deleteAccount();
        $this->actingAsAdmin();

        $this->get(ReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk()
            ->assertSee(Customer::DELETED_LABEL)
            ->assertDontSee('Maria Santos')
            ->assertDontSee('deleted.invalid')
            ->assertDontSee('Deleted User');

        // Table rows are rendered by the Livewire component, not the page shell.
        foreach ([ListReviews::class, ListReports::class] as $page) {
            Livewire::test($page)
                ->assertSee(Customer::DELETED_LABEL)
                ->assertDontSee('Maria Santos')
                ->assertDontSee('deleted.invalid')
                ->assertDontSee('Deleted User');
        }
    }

    // --- Active customers are unaffected -------------------------------------

    public function test_an_active_customer_is_shown_and_logs_in_as_before(): void
    {
        $customer = $this->customer();
        $this->actingAsAdmin();

        $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee(self::EMAIL)
            ->assertSee('09171234567')
            ->assertDontSee('Deleted customer details')
            ->assertDontSee(Customer::REMOVED_LABEL);

        $this->assertSame('Maria Santos', $customer->name);

        $this->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
