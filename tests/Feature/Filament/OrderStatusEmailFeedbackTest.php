<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Mail\OrderCancelledNoShowMail;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every status change emails the customer, so the admin who made it has to be
 * told what became of that email. Marking an order processing or ready for
 * pickup used to say nothing at all, and a mail failure threw out of the
 * action after the status had already been committed — leaving the admin with
 * a generic error and no idea whether the order had moved or the customer had
 * been told.
 */
class OrderStatusEmailFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-27 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => Customer::factory()->create(['email' => 'buyer@example.com'])->id,
            'subtotal' => 350,
            'total' => 350,
            'pickup_location' => 'UBAP_office',
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
    }

    private function actingAsAdmin(): User
    {
        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Assert on the sentence the admin actually reads, not only on the
     * heading. The session is read rather than mounted, because Filament's
     * own Notifications component pulls the notifications out of the session
     * as it mounts - which is also why assertNotified() cannot run before
     * this in the same test.
     */
    private function assertNotifiedWithBody(string $title, string $body): void
    {
        // A Livewire request moves what was sent into "claimed" on dehydrate.
        $sent = session()->get('filament.claimed_notifications')
            ?? session()->get('filament.notifications')
            ?? [];

        $notification = collect($sent)
            ->map(fn (array $sent): Notification => Notification::fromArray($sent))
            ->first(fn (Notification $sent): bool => $sent->getTitle() === $title);

        $this->assertNotNull($notification, "No notification titled [{$title}] was sent.");
        $this->assertSame($body, (string) $notification->getBody());
    }

    public function test_marking_processing_confirms_the_address_the_email_reached(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order();

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_processing', $order);

        Mail::assertSent(OrderProcessingMail::class);
        $this->assertNotifiedWithBody(
            'Order marked as processing',
            'The order is now being processed. Email sent to buyer@example.com.',
        );
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_marking_ready_for_pickup_confirms_the_email_and_the_claim_number(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order(['status' => 'processing']);

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_ready_for_pickup', $order, data: [
                'claim_number' => 'CLM-ABC123',
                'pickup_date' => '2026-09-30',
                'pickup_start_time' => '08:00',
                'pickup_end_time' => '17:00',
            ]);

        Mail::assertSent(OrderReadyForPickupMail::class);
        $this->assertNotifiedWithBody(
            'Order marked as ready for pickup',
            'Claim number CLM-ABC123 issued. Email sent to buyer@example.com.',
        );
    }

    public function test_completing_a_pickup_confirms_the_email_alongside_the_claimant_record(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order([
            'status' => 'ready_for_pickup',
            'pickup_date' => '2026-09-27',
            'pickup_slot' => '8:00 AM - 5:00 PM',
        ]);

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_completed', $order, data: [
                'claimant_name' => 'Juan Dela Cruz',
                'claimant_phone' => '09171234567',
                'or_number' => 'OR-2345',
            ]);

        Mail::assertSent(OrderCompletedMail::class);
        $this->assertNotifiedWithBody(
            'Order marked as completed',
            'Claimant details recorded. Email sent to buyer@example.com.',
        );
    }

    public function test_cancelling_a_no_show_confirms_the_email(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order([
            'status' => 'ready_for_pickup',
            'pickup_date' => '2026-09-26',
            'pickup_slot' => '8:00 AM - 5:00 PM',
        ]);

        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->callAction('cancel_no_show');

        Mail::assertSent(OrderCancelledNoShowMail::class);
        $this->assertNotifiedWithBody(
            'Order cancelled',
            'The reserved items were released. Email sent to buyer@example.com.',
        );
    }

    public function test_a_failed_send_warns_the_admin_and_keeps_the_status_change(): void
    {
        Log::spy();
        $this->actingAsAdmin();
        $order = $this->order();

        // The status write has already committed by the time the mailer is
        // reached, so the failure has to be reported, not thrown.
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP connection refused'));

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_processing', $order);

        $this->assertNotifiedWithBody(
            'Order marked as processing',
            'The order is now being processed. The email to buyer@example.com could not be sent. '
                .'Use "Resend email" on the order to try again, or contact the customer another way.',
        );
        $this->assertSame('processing', $order->fresh()->status);

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context): bool => $message === 'Order email failed to send.'
                && $context['recipient'] === 'buyer@example.com'
                && $context['mailable'] === OrderProcessingMail::class,
        )->once();
    }

    public function test_a_failed_send_can_be_retried_from_the_resend_action(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order(['status' => 'processing']);

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('resend_status_email', $order)
            ->callTableAction('resend_status_email', $order);

        Mail::assertSent(OrderProcessingMail::class, fn (OrderProcessingMail $mail): bool => $mail->hasTo('buyer@example.com'));
        $this->assertNotifiedWithBody('Order email resent', 'Email sent to buyer@example.com.');
    }

    public function test_resending_a_ready_order_carries_the_schedule_it_has_now(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->order([
            'status' => 'ready_for_pickup',
            'claim_number' => 'CLM-ABC123',
            'pickup_date' => '2026-09-28',
            'pickup_slot' => '8:00 AM - 5:00 PM',
        ]);
        $order->reschedulePickup('2026-09-30', '4:00 PM');

        Livewire::test(ListOrders::class)
            ->callTableAction('resend_status_email', $order);

        Mail::assertSent(
            OrderReadyForPickupMail::class,
            fn (OrderReadyForPickupMail $mail): bool => $mail->order->pickup_slot === '4:00 PM'
                && $mail->order->pickup_date->toDateString() === '2026-09-30',
        );
    }

    public function test_a_pending_order_has_no_email_to_resend(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('resend_status_email', $this->order());
    }

    /**
     * A customer who cancelled their own order must never be sent the
     * no-show message, which tells them they failed to collect it.
     */
    public function test_a_customer_cancelled_order_has_no_email_to_resend(): void
    {
        $this->actingAsAdmin();

        $order = $this->order([
            'status' => 'cancelled',
            'cancellation_reason' => 'changed_my_mind',
        ]);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('resend_status_email', $order);

        $this->assertNull(OrderResource::statusMailableFor($order));

        // The same order cancelled by an admin for a missed pickup does have
        // one, so it is the reason that decides this and not the status.
        $order->update(['cancellation_reason' => 'customer_no_show']);

        $this->assertInstanceOf(OrderCancelledNoShowMail::class, OrderResource::statusMailableFor($order));
    }
}
