<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OverduePickupHighlightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = new User;
        $admin->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ])->save();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Gate::before(fn () => true);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_highlight_appears_when_the_pickup_window_ends_and_clears_after_collection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 16:59:59', 'Asia/Manila'));

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 350,
            'total' => 350,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'pending',
            'status' => 'ready_for_pickup',
            'pickup_date' => '2026-09-30',
            'pickup_slot' => '8:00 AM - 5:00 PM',
        ]);

        $page = Livewire::test(ListOrders::class)
            ->set('activeTab', 'ready-for-pickup')
            ->assertCanSeeTableRecords([$order])
            ->assertDontSee('fi-order-pickup-overdue', false);

        Carbon::setTestNow(Carbon::parse('2026-09-30 17:00:00', 'Asia/Manila'));

        $page->call('$refresh')->assertSee('fi-order-pickup-overdue', false);
        $page->set('activeTab', 'all')->assertSee('fi-order-pickup-overdue', false);

        $order->update(['status' => 'completed', 'completed_at' => now()]);

        $page->call('$refresh')->assertDontSee('fi-order-pickup-overdue', false);
    }

    public function test_only_ready_orders_with_elapsed_valid_schedules_are_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 18:00:00', 'Asia/Manila'));
        $table = Livewire::test(ListOrders::class)->instance()->getTable();

        $cases = [
            ['ready_for_pickup', '2026-09-30', '8:00 AM - 5:00 PM', true],
            ['ready_for_pickup', '2026-09-29', '8:00 AM - 5:00 PM', true],
            ['ready_for_pickup', '2026-10-01', '8:00 AM - 5:00 PM', false],
            ['ready_for_pickup', '2026-09-30', '5:00 PM - 7:00 PM', false],
            ['ready_for_pickup', '2026-09-30', '4:00 PM', true],
            ['ready_for_pickup', null, '8:00 AM - 5:00 PM', false],
            ['ready_for_pickup', '2026-09-29', null, false],
            ['ready_for_pickup', '2026-09-29', 'Unscheduled', false],
            ['pending', '2026-09-29', '8:00 AM - 5:00 PM', false],
            ['processing', '2026-09-29', '8:00 AM - 5:00 PM', false],
            ['completed', '2026-09-29', '8:00 AM - 5:00 PM', false],
            ['cancelled', '2026-09-29', '8:00 AM - 5:00 PM', false],
        ];

        foreach ($cases as [$status, $date, $slot, $overdue]) {
            $order = new Order(['status' => $status, 'pickup_date' => $date, 'pickup_slot' => $slot]);

            $this->assertSame(
                $overdue ? ['fi-order-pickup-overdue'] : [],
                $table->getRecordClasses($order),
                "Unexpected highlight for {$status}, {$date}, {$slot}",
            );
        }
    }
}
