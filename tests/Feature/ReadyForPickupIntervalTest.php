<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ReadyForPickupIntervalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
        Mail::fake();
    }

    protected function makeProcessingOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 250,
            'total' => 250,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'processing',
        ]);
    }

    public function test_ready_for_pickup_saves_and_sends_a_time_interval(): void
    {
        $order = $this->makeProcessingOrder();

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_ready_for_pickup', $order, [
                'claim_number' => 'CLM-ABC123',
                'pickup_date' => '2026-09-10',
                'pickup_start_time' => '08:00',
                'pickup_end_time' => '17:00',
            ])
            ->assertHasNoActionErrors();

        $order->refresh();

        $this->assertSame('ready_for_pickup', $order->status);
        $this->assertSame('8:00 AM - 5:00 PM', $order->pickup_slot);
        Mail::assertSent(OrderReadyForPickupMail::class, fn ($mail) => $mail->order->is($order));

        $email = (new OrderReadyForPickupMail($order))->render();
        $this->assertStringContainsString('Pickup Time:', $email);
        $this->assertStringContainsString('8:00 AM - 5:00 PM', $email);

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Pickup Time')
            ->assertSee('8:00 AM - 5:00 PM');
    }

    public function test_pickup_end_time_must_be_later_than_start_time(): void
    {
        $order = $this->makeProcessingOrder();

        Livewire::test(ListOrders::class)
            ->callTableAction('mark_ready_for_pickup', $order, [
                'claim_number' => 'CLM-ABC123',
                'pickup_date' => '2026-09-10',
                'pickup_start_time' => '17:00',
                'pickup_end_time' => '08:00',
            ])
            ->assertHasActionErrors(['pickup_end_time' => 'after']);

        $this->assertSame('processing', $order->fresh()->status);
        Mail::assertNothingSent();
    }
}
