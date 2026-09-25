<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderRescheduledMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A customer who cannot come on their pickup day contacts UBAP, and UBAP
 * moves the pickup. The first schedule is kept for both sides to see, the
 * customer is emailed the change, and the claim number does not change.
 */
class OrderPickupRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function readyOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 350,
            'total' => 350,
            'pickup_location' => 'UBAP_office',
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'ready_for_pickup',
            'pickup_date' => '2026-09-26',
            'pickup_slot' => '8:00 AM - 5:00 PM',
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

    // --- Order::reschedulePickup() ------------------------------------------

    public function test_rescheduling_moves_the_pickup_and_keeps_the_original_schedule(): void
    {
        $order = $this->readyOrder();
        $claimNumber = $order->claim_number;

        $order = $order->reschedulePickup('2026-09-30', '4:00 PM', 'Customer is out of town');

        $this->assertSame('2026-09-30', $order->pickup_date->toDateString());
        $this->assertSame('4:00 PM', $order->pickup_slot);
        $this->assertSame('2026-09-26', $order->original_pickup_date->toDateString());
        $this->assertSame('8:00 AM - 5:00 PM', $order->original_pickup_slot);
        $this->assertTrue($order->rescheduled_at->equalTo(now()));
        $this->assertSame(1, $order->reschedule_count);
        $this->assertSame('ready_for_pickup', $order->status);
        $this->assertSame($claimNumber, $order->claim_number);

        $this->assertSame(
            'Pickup rescheduled from Sep 26, 2026 (8:00 AM - 5:00 PM) to Sep 30, 2026 (4:00 PM). Reason: Customer is out of town',
            $order->rescheduleHistories()->sole()->notes,
        );
    }

    public function test_a_second_reschedule_keeps_the_very_first_schedule_as_the_original(): void
    {
        $order = $this->readyOrder()
            ->reschedulePickup('2026-09-30', '4:00 PM')
            ->reschedulePickup('2026-10-02', '1:00 PM');

        $this->assertSame('2026-10-02', $order->pickup_date->toDateString());
        $this->assertSame('2026-09-26', $order->original_pickup_date->toDateString());
        $this->assertSame('8:00 AM - 5:00 PM', $order->original_pickup_slot);
        $this->assertSame(2, $order->reschedule_count);
        $this->assertCount(2, $order->rescheduleHistories);
    }

    public function test_rescheduling_moves_the_no_show_deadline_with_it(): void
    {
        Carbon::setTestNow('2026-09-26 18:00:00');
        $order = $this->readyOrder();
        $this->assertTrue($order->canBeCancelledForNoShow());

        $order = $order->reschedulePickup('2026-09-30', '5:00 PM');

        $this->assertFalse($order->canBeCancelledForNoShow());
    }

    public function test_a_past_date_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->readyOrder()->reschedulePickup('2026-09-24', '4:00 PM');
    }

    public function test_an_unchanged_schedule_is_rejected(): void
    {
        $order = $this->readyOrder()->reschedulePickup('2026-09-30', '4:00 PM');

        $this->expectException(ValidationException::class);

        $order->reschedulePickup('2026-09-30', '4:00 PM');
    }

    public function test_only_ready_for_pickup_orders_can_be_rescheduled(): void
    {
        foreach (['pending', 'processing', 'completed', 'cancelled'] as $status) {
            $order = $this->readyOrder(['status' => $status]);

            $this->assertFalse($order->canBeRescheduled(), $status);
            $this->assertNull($order->reschedulePickup('2026-09-30', '4:00 PM'), $status);
            $this->assertSame(0, $order->fresh()->reschedule_count, $status);
        }
    }

    // --- Admin panel ----------------------------------------------------------

    public function test_admin_reschedules_from_the_order_page_and_the_customer_is_emailed(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $order = $this->readyOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionVisible('reschedule_pickup')
            ->callAction('reschedule_pickup', data: [
                'pickup_date' => '2026-09-30',
                'pickup_time' => '16:00',
                'reason' => 'Customer called',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Pickup rescheduled');

        $order->refresh();
        $this->assertSame('2026-09-30', $order->pickup_date->toDateString());
        $this->assertSame('4:00 PM', $order->pickup_slot);
        $this->assertSame($admin->id, $order->rescheduleHistories()->sole()->user_id);

        Mail::assertSent(OrderRescheduledMail::class, function (OrderRescheduledMail $mail) use ($order): bool {
            return $mail->hasTo($order->customer->email)
                && $mail->previousPickupDate->toDateString() === '2026-09-26'
                && $mail->previousPickupSlot === '8:00 AM - 5:00 PM';
        });
    }

    public function test_admin_rescheduling_from_the_edit_page_refreshes_the_form(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->readyOrder();

        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->callAction('reschedule_pickup', data: [
                'pickup_date' => '2026-09-30',
                'pickup_time' => '17:00',
            ])
            ->assertNotified('Pickup rescheduled')
            ->assertSchemaStateSet(['pickup_date' => '2026-09-30']);

        Mail::assertSent(OrderRescheduledMail::class);
    }

    public function test_the_admin_order_page_shows_the_original_schedule_and_history(): void
    {
        $admin = $this->actingAsAdmin();
        $order = $this->readyOrder()->reschedulePickup('2026-09-30', '4:00 PM', 'Customer called', $admin->id);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertSee('Original date')
            ->assertSee('Sep 26, 2026')
            ->assertSee('Last rescheduled')
            ->assertSee('Maria Santos')
            ->assertSee('Reschedule history')
            ->assertSee('4:00 PM')
            ->assertSee('Customer called')
            // The note is split into columns, not echoed as one sentence.
            ->assertDontSee('Reason: Customer called');
    }

    public function test_a_reschedule_note_splits_into_its_parts(): void
    {
        $history = new OrderStatusHistory([
            'notes' => 'Pickup rescheduled from Sep 26, 2026 (8:00 AM - 5:00 PM) to Sep 30, 2026 (9:00 AM - 12:00 PM). Reason: Customer called',
        ]);

        $this->assertSame([
            'from_date' => 'Sep 26, 2026',
            'from_slot' => '8:00 AM - 5:00 PM',
            'to_date' => 'Sep 30, 2026',
            'to_slot' => '9:00 AM - 12:00 PM',
            'reason' => 'Customer called',
        ], $history->rescheduleDetails());

        $history->notes = 'Something else entirely';

        $this->assertSame('Something else entirely', $history->rescheduleDetails()['reason']);
        $this->assertNull($history->rescheduleDetails()['to_date']);
    }

    public function test_the_action_is_hidden_before_the_order_is_ready_for_pickup(): void
    {
        $this->actingAsAdmin();
        $order = $this->readyOrder(['status' => 'processing', 'pickup_date' => null, 'pickup_slot' => null]);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHidden('reschedule_pickup');
    }

    public function test_an_unchanged_schedule_is_refused_without_emailing(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $order = $this->readyOrder()->reschedulePickup('2026-09-30', '4:00 PM');

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('reschedule_pickup', data: [
                'pickup_date' => '2026-09-30',
                'pickup_time' => '16:00',
            ])
            ->assertNotified('Pickup not rescheduled');

        $this->assertSame(1, $order->fresh()->reschedule_count);
        Mail::assertNothingSent();
    }

    // --- What the customer sees ---------------------------------------------

    public function test_the_email_shows_the_new_and_previous_schedule(): void
    {
        $order = $this->readyOrder()->reschedulePickup('2026-09-30', '4:00 PM');

        $html = (new OrderRescheduledMail($order, Carbon::parse('2026-09-26'), '8:00 AM - 5:00 PM'))->render();

        $this->assertStringContainsString('Wednesday, September 30, 2026', $html);
        $this->assertStringContainsString('4:00 PM', $html);
        $this->assertStringContainsString('26/09/2026', $html);
        $this->assertStringContainsString('8:00 AM - 5:00 PM', $html);
        $this->assertStringContainsString($order->claim_number, $html);
    }

    public function test_the_customer_order_page_shows_the_new_and_original_schedule(): void
    {
        $order = $this->readyOrder()->reschedulePickup('2026-09-30', '4:00 PM');

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Pickup Rescheduled')
            ->assertSee('New schedule: Sep 30, 2026 · 4:00 PM')
            ->assertSee('Sep 26, 2026 · 8:00 AM - 5:00 PM');
    }

    public function test_the_notice_is_absent_for_an_order_that_was_never_rescheduled(): void
    {
        $order = $this->readyOrder();

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertDontSee('Pickup Rescheduled');
    }
}
