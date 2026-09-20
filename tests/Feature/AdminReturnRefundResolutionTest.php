<?php

namespace Tests\Feature;

use App\Enums\OrderItemResolutionReason;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ReturnRefunds\Pages\CreateReturnRefund;
use App\Filament\Resources\ReturnRefunds\Pages\ListReturnRefunds;
use App\Filament\Resources\ReturnRefunds\Pages\ViewReturnRefund;
use App\Filament\Resources\ReturnRefunds\ReturnRefundResource;
use App\Models\ReturnRefundResolution;
use App\Services\ReturnRefundResolutionService;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * The admin side, which lives under Returns & Refunds: listing what was
 * recorded, recording a refund or exchange through ReturnRefundResolutionService,
 * reading one back, and the links the order page keeps to all of it.
 * Permissions are real here rather than a blanket Gate::before, since who may
 * record is part of what is under test.
 */
class AdminReturnRefundResolutionTest extends TestCase
{
    use BuildsResolvableOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-13 10:00:00');
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * The form filled in as far as the order line and what UBAP did about it.
     * One field at a time, since choosing an order clears the line.
     */
    private function startRecording(int $orderId, int $itemId, string $type): Testable
    {
        return Livewire::test(CreateReturnRefund::class)
            ->fillForm(['order_id' => $orderId])
            ->fillForm(['order_item_id' => $itemId])
            ->fillForm(['type' => $type]);
    }

    public function test_an_order_admin_finds_recorded_refunds_and_exchanges_under_returns_and_refunds(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $refund = $this->refund($this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180])), 180);
        $exchange = $this->exchange($this->line($order, $shirt, $medium), $shirt, $large, 'damaged');

        $this->actingAs($this->adminWithPermissions(['ViewAny:Order', 'View:Order']));

        $this->assertSame('Returns & Refunds', ReturnRefundResource::getNavigationLabel());
        $this->assertTrue(ReturnRefundResource::canAccess());

        Livewire::test(ListReturnRefunds::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$refund, $exchange])
            ->assertSee($order->order_number)
            ->assertSee('CLSU Mug')
            ->assertSee('CLSU Shirt — Green / Medium')
            ->assertSee('CLSU Shirt — Green / Large')
            // Seeing them is not recording them.
            ->assertActionHidden('create');
    }

    public function test_returns_and_refunds_are_closed_to_an_admin_who_cannot_view_orders(): void
    {
        $this->actingAs($this->adminWithPermissions(['ViewAny:Product']));

        $this->assertFalse(ReturnRefundResource::canAccess());
        Livewire::test(ListReturnRefunds::class)->assertForbidden();
        Livewire::test(CreateReturnRefund::class)->assertForbidden();
    }

    public function test_recording_needs_the_record_resolution_permission(): void
    {
        $order = $this->completedOrder();
        $this->line($order, $this->product(['price' => 180]));
        $orderEditor = $this->adminWithPermissions(['ViewAny:Order', 'View:Order', 'Update:Order']);

        $this->actingAs($orderEditor);

        $this->assertTrue(ReturnRefundResource::canViewAny());
        $this->assertFalse(ReturnRefundResource::canCreate());
        Livewire::test(CreateReturnRefund::class)->assertForbidden();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertActionHidden('record_resolution');

        // The service refuses this admin on its own as well -- see
        // ReturnRefundResolutionServiceTest.
        $this->assertFalse($orderEditor->can('recordResolution', $order));
    }

    public function test_a_refund_is_recorded_through_the_service_against_the_order_line(): void
    {
        $admin = $this->recordingAdmin();
        $order = $this->completedOrder();
        $this->line($order, $this->product(['name' => 'CLSU Shirt', 'price' => 350]));
        $mug = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));

        $this->partialMock(ReturnRefundResolutionService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('recordRefund')->once()->passthru());

        $this->actingAs($admin);

        $page = $this->startRecording($order->id, $mug->id, 'refund')
            // Only what a refund needs.
            ->assertFormFieldIsVisible('refund_amount')
            ->assertFormFieldIsHidden('incorrect_product_id')
            ->assertFormFieldIsHidden('incorrect_item_condition')
            ->fillForm([
                'reason' => 'damaged',
                'quantity' => 1,
                'refund_amount' => 180,
                'processed_at' => '2026-09-13',
                'notes' => 'Chipped rim.',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Refund recorded');

        $resolution = ReturnRefundResolution::sole();
        $page->assertRedirect(ReturnRefundResource::getUrl('view', ['record' => $resolution]));

        $this->assertTrue($resolution->orderItem->is($mug));
        $this->assertTrue($resolution->orderItem->order->is($order));
        $this->assertDatabaseHas('return_refund_resolutions', [
            'order_item_id' => $mug->id,
            'type' => 'refund',
            'reason' => 'damaged',
            'quantity' => 1,
            'refund_amount' => 180,
            'notes' => 'Chipped rim.',
            'processed_by' => $admin->id,
            'incorrect_product_id' => null,
            'incorrect_item_condition' => null,
        ]);
    }

    public function test_an_exchange_for_a_wrong_item_released_hands_over_the_ordered_variant(): void
    {
        $admin = $this->recordingAdmin();
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        // Ordered in Medium, released in Large, which came back damaged.
        $line = $this->line($order, $shirt, $medium);

        $this->partialMock(ReturnRefundResolutionService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('recordExchange')->once()->passthru());

        $this->actingAs($admin);

        $this->startRecording($order->id, $line->id, 'exchange')
            ->fillForm(['reason' => 'seller_error'])
            ->assertFormFieldIsHidden('refund_amount')
            // The replacement is known from the line, so there is nothing to pick.
            ->assertFormFieldDoesNotExist('replacement_product_id')
            ->assertFormFieldDoesNotExist('replacement_variant_id')
            ->assertSchemaComponentStateSet('replacement', 'CLSU Shirt — Green / Medium')
            ->fillForm(['incorrect_product_id' => $shirt->id])
            ->assertFormFieldIsVisible('incorrect_variant_id')
            ->fillForm([
                'incorrect_variant_id' => $large->id,
                'incorrect_item_condition' => 'damaged',
                'quantity' => 1,
                'processed_at' => '2026-09-13',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Exchange recorded');

        $resolution = ReturnRefundResolution::sole();
        $this->assertTrue($resolution->orderItem->order->is($order));
        $this->assertDatabaseHas('return_refund_resolutions', [
            'order_item_id' => $line->id,
            'type' => 'exchange',
            'reason' => 'seller_error',
            'replacement_product_id' => $shirt->id,
            'replacement_variant_id' => $medium->id,
            'incorrect_product_id' => $shirt->id,
            'incorrect_variant_id' => $large->id,
            'incorrect_product_name' => 'CLSU Shirt',
            'incorrect_variant_name' => 'Green / Large',
            'incorrect_item_condition' => 'damaged',
            'processed_by' => $admin->id,
        ]);
        // The Medium is not taken out again; the damaged Large is written off.
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(3, $large->fresh()->stock_quantity);
    }

    public function test_an_exchange_cannot_be_pointed_at_a_replacement_other_than_the_ordered_item(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);

        $this->actingAs($this->recordingAdmin());

        // The form has no field for a replacement, so state for one goes nowhere.
        $this->startRecording($order->id, $line->id, 'exchange')
            ->fillForm(['reason' => 'seller_error'])
            ->fillForm([
                'replacement_product_id' => $shirt->id,
                'replacement_variant_id' => $large->id,
                'incorrect_product_id' => $shirt->id,
            ])
            ->fillForm([
                'incorrect_variant_id' => $large->id,
                'incorrect_item_condition' => 'sellable',
                'quantity' => 1,
                'processed_at' => '2026-09-13',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $resolution = ReturnRefundResolution::sole();
        $this->assertSame($shirt->id, $resolution->replacement_product_id);
        $this->assertSame($medium->id, $resolution->replacement_variant_id);
        $this->assertSame('Green / Medium', $resolution->replacement_variant_name);
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public static function reasonsUbapDoesNotAccept(): array
    {
        return [
            // Not UBAP's error, so not taken back at all.
            'the customer chose the wrong variant' => ['customer_error'],
            'change of mind' => ['change_of_mind'],
        ];
    }

    #[DataProvider('reasonsUbapDoesNotAccept')]
    public function test_an_exchange_is_refused_for_a_reason_ubap_does_not_accept(string $reason): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);

        $this->actingAs($this->recordingAdmin());

        $this->startRecording($order->id, $line->id, 'exchange')
            ->fillForm([
                'reason' => $reason,
                'quantity' => 1,
                'processed_at' => '2026-09-13',
            ])
            ->call('create')
            ->assertHasFormErrors(['reason']);

        $this->assertSame(0, ReturnRefundResolution::count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public static function everyReasonForEveryOutcome(): array
    {
        return [
            'refund for defective' => ['refund', 'defective'],
            'refund for damaged' => ['refund', 'damaged'],
            'refund for seller error' => ['refund', 'seller_error'],
            'exchange for defective' => ['exchange', 'defective'],
            'exchange for damaged' => ['exchange', 'damaged'],
            'exchange for seller error' => ['exchange', 'seller_error'],
        ];
    }

    #[DataProvider('everyReasonForEveryOutcome')]
    public function test_every_reason_can_be_recorded_as_a_refund_or_an_exchange(string $type, string $reason): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);

        $this->actingAs($this->recordingAdmin());

        $page = $this->startRecording($order->id, $line->id, $type)
            ->fillForm(['reason' => $reason, 'quantity' => 1, 'processed_at' => '2026-09-13']);

        if ($type === 'refund') {
            $page->fillForm(['refund_amount' => 350]);
        } elseif ($reason === 'seller_error') {
            $page->fillForm(['incorrect_product_id' => $shirt->id])
                ->fillForm(['incorrect_variant_id' => $large->id, 'incorrect_item_condition' => 'sellable']);
        }

        $page->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(ucfirst($type).' recorded');

        $this->assertDatabaseHas('return_refund_resolutions', [
            'order_item_id' => $line->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => 1,
        ]);
    }

    public function test_switching_the_outcome_keeps_the_reason_and_offers_every_reason(): void
    {
        $order = $this->completedOrder();
        $line = $this->line($order, $this->product(['price' => 180]));

        $everyReason = collect(OrderItemResolutionReason::cases())
            ->mapWithKeys(fn (OrderItemResolutionReason $reason): array => [$reason->value => $reason->getLabel()])
            ->all();
        $offersEveryReason = fn (Select $field): bool => $field->getOptions() === $everyReason;

        $this->actingAs($this->recordingAdmin());

        foreach (OrderItemResolutionReason::cases() as $reason) {
            $this->startRecording($order->id, $line->id, 'refund')
                ->assertFormFieldExists('reason', $offersEveryReason)
                ->fillForm(['reason' => $reason->value])
                ->fillForm(['type' => 'exchange'])
                ->assertFormSet(['reason' => $reason->value])
                ->assertFormFieldExists('reason', $offersEveryReason)
                ->fillForm(['type' => 'refund'])
                ->assertFormSet(['reason' => $reason->value])
                ->assertFormFieldExists('reason', $offersEveryReason);
        }
    }

    public static function faultyItemReasons(): array
    {
        return [
            'defective' => ['defective'],
            'damaged' => ['damaged'],
        ];
    }

    #[DataProvider('faultyItemReasons')]
    public function test_an_exchange_for_a_defective_or_damaged_item_asks_nothing_about_an_item_released_in_error(string $reason): void
    {
        $admin = $this->recordingAdmin();
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium, quantity: 2);

        $this->actingAs($admin);

        $this->startRecording($order->id, $line->id, 'exchange')
            // Filled in as seller error first, then the reason corrected.
            ->fillForm(['reason' => 'seller_error'])
            ->assertFormFieldIsVisible('incorrect_product_id')
            ->assertFormFieldIsVisible('incorrect_item_condition')
            ->fillForm(['incorrect_product_id' => $shirt->id])
            ->fillForm(['incorrect_variant_id' => $large->id, 'incorrect_item_condition' => 'damaged'])
            ->fillForm(['reason' => $reason])
            ->assertFormSet(['reason' => $reason])
            ->assertFormFieldIsHidden('incorrect_product_id')
            ->assertFormFieldIsHidden('incorrect_variant_id')
            ->assertFormFieldIsHidden('incorrect_item_condition')
            ->assertFormFieldIsHidden('refund_amount')
            ->assertSchemaComponentStateSet('replacement', 'CLSU Shirt — Green / Medium')
            ->fillForm(['quantity' => 2, 'processed_at' => '2026-09-13'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Exchange recorded');

        $this->assertDatabaseHas('return_refund_resolutions', [
            'order_item_id' => $line->id,
            'type' => 'exchange',
            'reason' => $reason,
            'quantity' => 2,
            'replacement_product_id' => $shirt->id,
            'replacement_variant_id' => $medium->id,
            'incorrect_product_id' => null,
            'incorrect_variant_id' => null,
            'incorrect_product_name' => null,
            'incorrect_variant_name' => null,
            'incorrect_item_condition' => null,
            'processed_by' => $admin->id,
        ]);
        // Two more Mediums handed over; the Large named along the way is untouched.
        $this->assertSame(3, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public function test_an_exchange_for_a_defective_item_is_refused_when_the_replacement_is_not_in_stock(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 1, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium, quantity: 2);

        $this->actingAs($this->recordingAdmin());

        $this->startRecording($order->id, $line->id, 'exchange')
            ->fillForm(['reason' => 'defective'])
            ->fillForm(['quantity' => 2, 'processed_at' => '2026-09-13'])
            ->call('create')
            ->assertHasFormErrors(['quantity'])
            ->assertNotified('Exchange not recorded');

        $this->assertSame(0, ReturnRefundResolution::count());
        $this->assertSame(1, $medium->fresh()->stock_quantity);
    }

    public function test_what_the_service_refuses_is_reported_against_the_field_responsible(): void
    {
        $mug = $this->product(['name' => 'CLSU Mug', 'stock_quantity' => 6]);
        $order = $this->completedOrder();
        $line = $this->line($order, $mug);

        $this->actingAs($this->recordingAdmin());

        // The ordered item cannot also be the one released in error.
        $this->startRecording($order->id, $line->id, 'exchange')
            ->fillForm(['reason' => 'seller_error'])
            ->fillForm(['incorrect_product_id' => $mug->id])
            // A product sold without variants has no variant to name.
            ->assertFormFieldIsHidden('incorrect_variant_id')
            ->fillForm([
                'incorrect_item_condition' => 'damaged',
                'quantity' => 1,
                'processed_at' => '2026-09-13',
            ])
            ->call('create')
            ->assertHasFormErrors(['incorrect_product_id'])
            ->assertNotified('Exchange not recorded');

        $this->assertSame(0, ReturnRefundResolution::count());
        $this->assertSame(6, $mug->fresh()->stock_quantity);
    }

    public function test_a_refund_is_held_to_the_units_left_and_what_they_cost(): void
    {
        $order = $this->completedOrder();
        $line = $this->line($order, $this->product(['price' => 350]), quantity: 2);
        // One of the two units was refunded already.
        $this->refund($line, 350);

        $this->actingAs($this->recordingAdmin());

        $page = $this->startRecording($order->id, $line->id, 'refund')
            ->fillForm(['reason' => 'defective', 'processed_at' => '2026-09-13']);

        $page->fillForm(['quantity' => 2, 'refund_amount' => 700])
            ->call('create')
            ->assertHasFormErrors(['quantity']);

        $page->fillForm(['quantity' => 1, 'refund_amount' => 350.01])
            ->call('create')
            ->assertHasFormErrors(['refund_amount'])
            ->assertNotified('Refund not recorded');

        $this->assertSame(1, ReturnRefundResolution::count());
    }

    public function test_a_recorded_exchange_reads_back_in_full_and_links_to_its_order(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);
        $resolution = $this->exchange($line, $shirt, $large, 'damaged', extra: [
            'processed_at' => '2026-09-12',
            'notes' => 'Released a Large by mistake.',
        ]);

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ViewReturnRefund::class, ['record' => $resolution->getRouteKey()])
            ->assertOk()
            ->assertSeeInOrder([
                'Exchange',
                'Seller error',
                'Sep 12, 2026',
                'Maria Santos',
                'Released a Large by mistake.',
                $order->order_number,
                'CLSU Shirt',
                'Green / Medium',
                'CLSU Shirt — Green / Medium',
                'CLSU Shirt — Green / Large',
                'Damaged',
            ])
            ->assertActionVisible('open_order')
            ->assertActionHasUrl('open_order', OrderResource::getUrl('view', ['record' => $order]));
    }

    public function test_a_recorded_exchange_for_a_damaged_item_reads_back_without_an_item_released_in_error(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $resolution = $this->exchangeFaultyItem($this->line($order, $shirt, $medium), 'damaged');

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ViewReturnRefund::class, ['record' => $resolution->getRouteKey()])
            ->assertOk()
            ->assertSeeInOrder(['Exchange', 'Damaged', $order->order_number, 'CLSU Shirt — Green / Medium'])
            ->assertSee('The customer was given another unit of the item they ordered.')
            ->assertDontSee('UBAP released the wrong item')
            ->assertDontSee('Item released in error')
            ->assertDontSee('Condition when returned');
    }

    public function test_a_recorded_resolution_cannot_be_edited_or_deleted_through_returns_and_refunds(): void
    {
        $resolution = $this->refund($this->line($this->completedOrder(), $this->product(['price' => 180])), 180);

        // Every order permission there is, and still no way to change the record.
        $this->actingAs($this->adminWithPermissions([
            'ViewAny:Order', 'View:Order', 'Create:Order', 'Update:Order', 'Delete:Order', 'Restore:Order',
            'ForceDelete:Order', 'ForceDeleteAny:Order', 'RestoreAny:Order', 'Replicate:Order', 'Reorder:Order',
            'RecordResolution:Order',
        ]));

        $this->assertSame(['index', 'create', 'view'], array_keys(ReturnRefundResource::getPages()));
        $this->assertTrue(Route::has('filament.admin.resources.return-refunds.view'));
        $this->assertFalse(Route::has('filament.admin.resources.return-refunds.edit'));

        $this->assertFalse(ReturnRefundResource::canEdit($resolution));
        $this->assertFalse(ReturnRefundResource::canDelete($resolution));
        $this->assertFalse(ReturnRefundResource::canDeleteAny());
        $this->assertFalse(ReturnRefundResource::canForceDelete($resolution));
        $this->assertFalse(ReturnRefundResource::canForceDeleteAny());
        $this->assertFalse(ReturnRefundResource::canReplicate($resolution));

        Livewire::test(ListReturnRefunds::class)
            ->assertCanSeeTableRecords([$resolution])
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('forceDelete');

        Livewire::test(ViewReturnRefund::class, ['record' => $resolution->getRouteKey()])
            ->assertOk()
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');

        $this->assertSame('180.00', $resolution->fresh()->refund_amount);
    }

    public function test_an_older_record_without_the_newer_details_still_lists_and_reads(): void
    {
        $order = $this->completedOrder();
        $line = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));

        // An exchange carried over from before the item released in error was
        // recorded, with no admin on file -- written the way a data migration
        // would, not through the service.
        $legacy = ReturnRefundResolution::create([
            'order_item_id' => $line->id,
            'type' => 'exchange',
            'reason' => 'seller_error',
            'quantity' => 1,
            'replacement_product_id' => null,
            'replacement_product_name' => 'CLSU Mug',
            'processed_at' => '2026-09-12',
        ]);
        // And its order has since been deleted.
        $order->delete();

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ListReturnRefunds::class)
            ->assertCanSeeTableRecords([$legacy])
            ->assertSee($order->order_number)
            ->assertSee('Admin account removed');

        Livewire::withQueryParams(['search' => $order->order_number])
            ->test(ListReturnRefunds::class)
            ->assertCanSeeTableRecords([$legacy]);

        Livewire::test(ViewReturnRefund::class, ['record' => $legacy->getRouteKey()])
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('CLSU Mug')
            ->assertSee('Not recorded')
            ->assertSee('Admin account removed');
    }

    public function test_the_order_page_links_to_record_a_refund_or_exchange_with_the_order_chosen(): void
    {
        $order = $this->completedOrder();
        $line = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('record_resolution')
            ->assertActionHasUrl('record_resolution', ReturnRefundResource::getUrl('create', ['order' => $order->id]))
            // Nothing is recorded from the order page itself any more.
            ->assertActionDoesNotExist('record_refund')
            ->assertActionDoesNotExist('record_exchange');

        Livewire::withQueryParams(['order' => $order->id])
            ->test(CreateReturnRefund::class)
            ->assertFormSet(['order_id' => $order->id])
            ->fillForm(['order_item_id' => $line->id, 'type' => 'refund'])
            ->fillForm(['reason' => 'damaged', 'quantity' => 1, 'refund_amount' => 180, 'processed_at' => '2026-09-13'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ReturnRefundResolution::sole()->orderItem->order->is($order));
    }

    public function test_the_record_link_is_not_offered_before_the_order_is_collected(): void
    {
        $order = $this->completedOrder(['status' => 'ready_for_pickup', 'completed_at' => null]);
        $this->line($order, $this->product());

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertActionHidden('record_resolution');

        // Nor does linking there by hand start on that order.
        Livewire::withQueryParams(['order' => $order->id])
            ->test(CreateReturnRefund::class)
            ->assertFormSet(['order_id' => null]);
    }

    public function test_the_order_page_summarises_each_lines_outcomes_and_links_to_the_full_records(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $shirtLine = $this->line($order, $shirt, $medium);
        $mugLine = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180]));
        $exchange = $this->exchange($shirtLine, $shirt, $large, 'damaged', extra: [
            'processed_at' => '2026-09-12',
            'notes' => 'Released a Large by mistake.',
        ]);
        $refund = $this->refund($mugLine, 180, extra: ['reason' => 'damaged', 'processed_at' => '2026-09-13']);
        $elsewhere = $this->refund($this->line($this->completedOrder(), $this->product(['price' => 180])), 180);

        $this->actingAs($this->recordingAdmin());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSeeInOrder(['CLSU Shirt', 'Exchanged ×1 · Sep 12, 2026', 'CLSU Mug', 'Refunded ×1 · Sep 13, 2026'])
            // The detail lives under Returns & Refunds.
            ->assertDontSee('Wrong item released')
            ->assertDontSee('Released a Large by mistake.')
            ->assertActionVisible('view_resolutions')
            ->assertActionHasUrl('view_resolutions', ReturnRefundResource::getUrl('index', ['search' => $order->order_number]));

        // Which opens on this order's records and nobody else's.
        Livewire::withQueryParams(['search' => $order->order_number])
            ->test(ListReturnRefunds::class)
            ->assertCanSeeTableRecords([$exchange, $refund])
            ->assertCanNotSeeTableRecords([$elsewhere]);
    }

    public function test_orders_with_recorded_resolutions_appear_under_the_returns_tab(): void
    {
        $resolved = $this->completedOrder();
        $this->refund($this->line($resolved, $this->product(['price' => 180])), 180);
        $untouched = $this->completedOrder();
        $this->line($untouched, $this->product());

        $this->actingAs($this->adminWithPermissions(['ViewAny:Order', 'View:Order']));

        $component = Livewire::test(ListOrders::class);
        $this->assertSame('1', $component->instance()->getCachedTabs()['returns']->getBadge());

        $component->set('activeTab', 'returns')
            ->assertCanSeeTableRecords([$resolved])
            ->assertCanNotSeeTableRecords([$untouched]);
    }

    public function test_recording_a_resolution_is_a_shield_permission_listed_under_orders(): void
    {
        $resources = FilamentShield::getResources();

        $this->assertSame('RecordResolution:Order', $resources[OrderResource::class]['permissions']['recordResolution']['key'] ?? null);
        // Returns & Refunds borrows the order permissions, so it has none of its own.
        $this->assertArrayNotHasKey(ReturnRefundResource::class, $resources);
    }

    public function test_a_customer_session_cannot_open_the_admin_pages(): void
    {
        $order = $this->completedOrder();

        $this->actingAs($order->customer, 'customer')
            ->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertRedirect();

        $this->actingAs($order->customer, 'customer')
            ->get(ReturnRefundResource::getUrl('index'))
            ->assertRedirect();
    }
}
