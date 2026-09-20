<?php

namespace Tests\Feature;

use App\Livewire\Customer\OrderDetails;
use App\Livewire\Customer\Orders;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * Customers no longer ask for returns through the shop. They take it up with
 * UBAP directly, and all the shop shows them is the outcome once it has been
 * recorded -- on their own orders and nobody else's.
 */
class CustomerOrderResolutionTest extends TestCase
{
    use BuildsResolvableOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_completed_order_offers_no_return_or_refund_request(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Mug']));

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSee('CLSU Mug')
            ->assertDontSee('Request Return')
            ->assertDontSee('Refund Request')
            ->assertDontSee('requestReturn')
            ->assertSee('For return, refund, or exchange concerns, please contact the UBAP Office directly');
    }

    public function test_the_retired_return_request_action_cannot_be_called(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product());

        $this->assertThrows(
            fn () => Livewire::actingAs($order->customer, 'customer')
                ->test(OrderDetails::class, ['id' => $order->id])
                ->call('requestReturn'),
            MethodNotFoundException::class,
        );

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->id,
        ]);
    }

    public function test_there_is_no_customer_route_or_page_for_return_requests(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => (bool) preg_match('/refund|return/i', $uri))
            ->values()
            ->all();

        // The static policy page is all that is left under that name.
        $this->assertSame(['return-refund-policy'], $uris);
        $this->assertFalse(class_exists('App\\Livewire\\RequestRefundPage'));

        // Nowhere on the order page to attach photo or video evidence.
        $this->assertNotContains(WithFileUploads::class, class_uses_recursive(OrderDetails::class));
    }

    public function test_a_customer_cannot_record_an_outcome(): void
    {
        $order = $this->completedOrder();

        $this->assertFalse($order->customer->can('recordResolution', $order));
    }

    public function test_the_owner_sees_a_recorded_refund_on_the_order_page(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Shirt', 'price' => 350]));
        $mug = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));
        $this->refund($mug, 180, extra: [
            'reason' => 'damaged',
            'processed_at' => '2026-09-13',
            'notes' => 'Internal: approved over email by the office head.',
        ]);

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            // The order itself is still the completed sale it was.
            ->assertSee('Completed')
            ->assertSeeInOrder([
                'CLSU Mug',
                'Refunded',
                'Reason: Damaged',
                'Refund Amount: ₱180.00',
                'Processed: September 13, 2026',
            ])
            // Admin notes are internal.
            ->assertDontSee('Internal: approved over email by the office head.');
    }

    public function test_the_owner_sees_a_recorded_exchange_on_the_order_page(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);
        // Ordered in Medium, released in Large.
        $this->exchange($line, $shirt, $large, extra: ['processed_at' => '2026-09-12']);

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertSeeInOrder([
                'CLSU Shirt',
                'Exchanged',
                'Reason: Seller error',
                'Replacement: Green / Medium',
                'Processed: September 12, 2026',
            ])
            ->assertDontSee('Green / Large');
    }

    public function test_lines_without_a_recorded_outcome_show_no_resolution(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Mug']));

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertOk()
            ->assertDontSee('Refunded')
            ->assertDontSee('Exchanged')
            ->assertDontSee('Replacement:');
    }

    public function test_order_history_flags_the_lines_that_were_resolved(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Shirt']));
        $mug = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));
        $this->refund($mug, 180);

        $this->actingAs($order->customer, 'customer')
            ->get(route('customer.orders'))
            ->assertOk()
            ->assertSeeInOrder([$order->order_number, 'CLSU Mug', 'Refunded']);
    }

    public function test_another_customer_cannot_see_the_outcome(): void
    {
        $order = $this->completedOrder();
        $this->refund($this->line($order, $this->product(['price' => 180])), 180);

        $intruder = Customer::factory()->create();
        $this->line($this->completedOrder(customer: $intruder), $this->product(['name' => 'CLSU Pen']));

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.show', $order->id))
            ->assertNotFound();

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders'))
            ->assertOk()
            ->assertSee('CLSU Pen')
            ->assertDontSee($order->order_number)
            ->assertDontSee('Refunded');
    }

    /**
     * Count the queries that reach the resolutions table while the order
     * history renders, after adding more resolved orders.
     */
    private function resolutionQueriesToRenderHistory(Customer $customer, int $extraOrders): int
    {
        for ($i = 0; $i < $extraOrders; $i++) {
            $order = $this->completedOrder(customer: $customer);
            $this->refund($this->line($order, $this->product(['price' => 180])), 180);
            $this->line($order, $this->product());
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        Livewire::test(Orders::class)->assertOk();
        $queries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'return_refund_resolutions'),
        );
        DB::disableQueryLog();

        return count($queries);
    }

    public function test_order_history_does_not_query_resolutions_once_per_line(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $twoOrders = $this->resolutionQueriesToRenderHistory($customer, 2);
        $sixOrders = $this->resolutionQueriesToRenderHistory($customer, 4);

        $this->assertSame($twoOrders, $sixOrders, sprintf(
            'Rendering the order history ran %d resolution queries for 2 orders and %d for 6. '
            .'Eager load items.resolutions on the query feeding the page.',
            $twoOrders,
            $sixOrders,
        ));
    }
}
