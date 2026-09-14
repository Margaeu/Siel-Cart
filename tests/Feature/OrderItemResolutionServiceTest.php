<?php

namespace Tests\Feature;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Enums\ReturnedItemCondition;
use App\Models\OrderItemResolution;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * UBAP settles returns outside the shop -- by email or at the counter -- and
 * an admin records the final outcome here afterwards. These cover the rules
 * that record has to obey: it belongs to one order line, it never rewrites the
 * sale, and an exchange -- for any reason UBAP accepts -- hands over the item
 * that was ordered. For seller error, stock only comes off the wrong item
 * released, and only when it comes back unfit to sell. For a defective or
 * damaged item, the replacement comes off the ordered item's own stock.
 */
class OrderItemResolutionServiceTest extends TestCase
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

    /** @return array{0: Product, 1: ProductVariant} */
    private function hoodieInBlackMedium(int $stock = 7): array
    {
        $hoodie = $this->product(['name' => 'CLSU Hoodie', 'has_variants' => true, 'price' => 650, 'stock_quantity' => 0]);

        return [$hoodie, ProductVariant::factory()->for($hoodie)->create([
            'name' => 'Black / Medium',
            'price' => 650,
            'stock_quantity' => $stock,
            'is_active' => true,
        ])];
    }

    public function test_a_refund_is_recorded_against_one_line_and_leaves_the_sale_intact(): void
    {
        $admin = $this->recordingAdmin();
        $order = $this->completedOrder();
        $shirt = $this->line($order, $this->product(['name' => 'CLSU Shirt', 'price' => 350]));
        $mug = $this->line($order, $this->product(['name' => 'CLSU Mug', 'price' => 180, 'stock_quantity' => 6]));
        $keychain = $this->line($order, $this->product(['name' => 'CLSU Keychain', 'price' => 50]));
        $linesBefore = $order->items()->get()->map->getAttributes()->all();

        $resolution = $this->resolutions()->recordRefund($mug, $admin, [
            'reason' => 'damaged',
            'quantity' => 1,
            'refund_amount' => 180,
            'notes' => 'Cracked handle, confirmed at the counter.',
            'processed_at' => '2026-09-12',
        ])->fresh();

        $this->assertTrue($resolution->orderItem->is($mug));
        $this->assertSame(OrderItemResolutionType::Refund, $resolution->type);
        $this->assertSame(OrderItemResolutionReason::Damaged, $resolution->reason);
        $this->assertSame(1, $resolution->quantity);
        $this->assertSame('180.00', $resolution->refund_amount);
        $this->assertSame('Cracked handle, confirmed at the counter.', $resolution->notes);
        $this->assertSame($admin->id, $resolution->processed_by);
        $this->assertSame('2026-09-12', $resolution->processed_at->toDateString());
        $this->assertNull($resolution->replacement_variant_id);
        // A refund has no wrong item to name.
        $this->assertNull($resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_item_label);
        $this->assertNull($resolution->incorrect_item_condition);

        // Only the refunded line carries the record.
        $this->assertCount(0, $shirt->resolutions);
        $this->assertCount(0, $keychain->resolutions);

        // The sale still reads exactly as it was made.
        $this->assertSame($linesBefore, $order->items()->get()->map->getAttributes()->all());
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('580.00', $order->total);

        // A refund hands back money. Nothing goes back on the shelf.
        $this->assertSame(6, $mug->product->fresh()->stock_quantity);
    }

    public function test_a_wrong_variant_returned_sellable_is_recorded_without_touching_stock(): void
    {
        $admin = $this->recordingAdmin();
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium);
        $lineBefore = $line->fresh()->getAttributes();

        // Ordered in Medium; UBAP released a Large, which came back unworn.
        $resolution = $this->resolutions()->recordExchange($line, $admin, [
            'reason' => 'seller_error',
            'quantity' => 1,
            'incorrect_product_id' => $shirt->id,
            'incorrect_variant_id' => $large->id,
            'incorrect_item_condition' => 'sellable',
            'notes' => 'Released a Large by mistake.',
            'processed_at' => '2026-09-13',
        ])->fresh();

        $this->assertSame(OrderItemResolutionType::Exchange, $resolution->type);
        $this->assertSame(OrderItemResolutionReason::SellerError, $resolution->reason);
        // The replacement comes from the line, never from the input.
        $this->assertSame($shirt->id, $resolution->replacement_product_id);
        $this->assertSame($medium->id, $resolution->replacement_variant_id);
        $this->assertSame('Green / Medium', $resolution->replacement_label);
        $this->assertSame($shirt->id, $resolution->incorrect_product_id);
        $this->assertSame($large->id, $resolution->incorrect_variant_id);
        $this->assertSame('CLSU Shirt — Green / Large', $resolution->incorrect_item_label);
        $this->assertSame(ReturnedItemCondition::Sellable, $resolution->incorrect_item_condition);
        $this->assertNull($resolution->refund_amount);
        $this->assertSame($admin->id, $resolution->processed_by);

        // Medium came out of stock at checkout, and Large never did.
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);

        $this->assertSame($lineBefore, $line->fresh()->getAttributes());
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_a_different_product_returned_sellable_is_recorded_without_touching_stock(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        [$hoodie, $blackMedium] = $this->hoodieInBlackMedium(stock: 7);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $resolution = $this->exchange($line, $hoodie, $blackMedium, 'sellable')->fresh();

        $this->assertSame($medium->id, $resolution->replacement_variant_id);
        $this->assertSame('Green / Medium', $resolution->replacement_label);
        $this->assertSame($hoodie->id, $resolution->incorrect_product_id);
        $this->assertSame($blackMedium->id, $resolution->incorrect_variant_id);
        $this->assertSame('CLSU Hoodie — Black / Medium', $resolution->incorrect_item_label);
        $this->assertSame(ReturnedItemCondition::Sellable, $resolution->incorrect_item_condition);

        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(7, $blackMedium->fresh()->stock_quantity);
    }

    public static function unsellableConditions(): array
    {
        return [
            'damaged' => ['damaged', ReturnedItemCondition::Damaged],
            'defective' => ['defective', ReturnedItemCondition::Defective],
            'defective, as the form hands it over' => [ReturnedItemCondition::Defective, ReturnedItemCondition::Defective],
        ];
    }

    #[DataProvider('unsellableConditions')]
    public function test_a_wrong_item_returned_damaged_or_defective_is_written_off_its_own_stock(ReturnedItemCondition|string $condition, ReturnedItemCondition $expected): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $resolution = $this->exchange($line, $shirt, $large, extra: ['incorrect_item_condition' => $condition])->fresh();

        $this->assertSame($expected, $resolution->incorrect_item_condition);
        $this->assertSame('CLSU Shirt — Green / Large', $resolution->incorrect_item_label);
        // The Large will not be sold again.
        $this->assertSame(3, $large->fresh()->stock_quantity);
        $this->assertSame(5, $medium->fresh()->stock_quantity);
    }

    public function test_an_unsellable_wrong_product_sold_without_variants_is_written_off_its_own_stock(): void
    {
        $mug = $this->product(['name' => 'CLSU Mug', 'price' => 180, 'stock_quantity' => 6]);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'price' => 220, 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $mug, quantity: 2);

        $resolution = $this->exchange($line, $tumbler, condition: 'damaged', quantity: 2)->fresh();

        $this->assertSame(7, $tumbler->fresh()->stock_quantity);
        $this->assertSame(6, $mug->fresh()->stock_quantity);
        $this->assertSame($mug->id, $resolution->replacement_product_id);
        $this->assertNull($resolution->replacement_variant_id);
        $this->assertSame('CLSU Mug', $resolution->replacement_label);
        $this->assertSame($tumbler->id, $resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_variant_id);
        $this->assertSame('CLSU Tumbler', $resolution->incorrect_item_label);
    }

    public static function everyCondition(): array
    {
        return [
            'sellable' => ['sellable'],
            'damaged' => ['damaged'],
            'defective' => ['defective'],
        ];
    }

    #[DataProvider('everyCondition')]
    public function test_the_ordered_item_is_not_deducted_a_second_time(string $condition): void
    {
        // Checkout took the last two Mediums for this order, so none are on record.
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 0, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium, quantity: 2);

        $this->exchange($line, $shirt, $large, $condition, quantity: 2);

        $this->assertSame(1, OrderItemResolution::count());
        $this->assertSame(0, $medium->fresh()->stock_quantity);
    }

    public function test_the_ordered_variant_cannot_be_recorded_as_the_item_released_in_error(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $this->assertRejected('incorrect_variant_id', fn () => $this->exchange($line, $shirt, $medium, 'damaged'));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public function test_the_ordered_product_cannot_be_recorded_as_the_item_released_in_error(): void
    {
        $mug = $this->product(['name' => 'CLSU Mug', 'stock_quantity' => 6]);
        $line = $this->line($this->completedOrder(), $mug);

        $this->assertRejected('incorrect_product_id', fn () => $this->exchange($line, $mug, condition: 'damaged'));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(6, $mug->fresh()->stock_quantity);
    }

    public static function incompleteWrongItemInput(): array
    {
        return [
            'no item released in error' => ['incorrect_product_id', ['incorrect_product_id' => null, 'incorrect_variant_id' => null]],
            'an item not in the catalogue' => ['incorrect_product_id', ['incorrect_product_id' => 999999, 'incorrect_variant_id' => null]],
            'no condition' => ['incorrect_item_condition', ['incorrect_item_condition' => null]],
            'an unknown condition' => ['incorrect_item_condition', ['incorrect_item_condition' => 'like_new']],
        ];
    }

    #[DataProvider('incompleteWrongItemInput')]
    public function test_an_exchange_must_name_the_item_released_in_error_and_its_condition(string $field, array $override): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $this->assertRejected($field, fn () => $this->exchange($line, $shirt, $large, 'damaged', extra: $override));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public function test_a_trashed_product_is_not_a_valid_item_released_in_error(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $tumbler->delete();

        $this->assertRejected('incorrect_product_id', fn () => $this->exchange($line, $tumbler, condition: 'damaged'));

        $this->assertSame(9, Product::withTrashed()->find($tumbler->id)->stock_quantity);
    }

    public function test_the_variant_released_in_error_must_be_named_and_belong_to_that_product(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        [$hoodie, $blackMedium] = $this->hoodieInBlackMedium(stock: 7);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        // A product sold by variant has to say which one went out.
        $this->assertRejected('incorrect_variant_id', fn () => $this->exchange($line, $hoodie, condition: 'damaged'));
        // A shirt size is not a hoodie.
        $this->assertRejected('incorrect_variant_id', fn () => $this->exchange($line, $hoodie, $large, 'damaged'));
        // A product sold without variants has none to name.
        $this->assertRejected('incorrect_variant_id', fn () => $this->exchange($line, $tumbler, $large, 'damaged'));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(4, $large->fresh()->stock_quantity);
        $this->assertSame(7, $blackMedium->fresh()->stock_quantity);
        $this->assertSame(9, $tumbler->fresh()->stock_quantity);
    }

    public function test_a_write_off_the_recorded_stock_cannot_cover_changes_nothing(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 1);
        $line = $this->line($this->completedOrder(), $shirt, $medium, quantity: 2);

        $this->assertRejected('quantity', fn () => $this->exchange($line, $shirt, $large, 'damaged', quantity: 2));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(1, $large->fresh()->stock_quantity);

        // Back in sellable condition there is nothing to write off.
        $this->exchange($line, $shirt, $large, 'sellable', quantity: 2);
        $this->assertSame(1, $large->fresh()->stock_quantity);
    }

    public function test_naming_the_ordered_product_and_variant_as_the_replacement_is_accepted(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $resolution = $this->exchange($line, $shirt, $large, extra: [
            'replacement_product_id' => $shirt->id,
            'replacement_variant_id' => $medium->id,
        ]);

        $this->assertSame($medium->id, $resolution->replacement_variant_id);
        $this->assertSame(5, $medium->fresh()->stock_quantity);
    }

    public function test_the_replacement_cannot_be_anything_but_the_ordered_item(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        [$hoodie, $blackMedium] = $this->hoodieInBlackMedium();
        $mug = $this->product(['name' => 'CLSU Mug', 'stock_quantity' => 6]);
        $shirtLine = $this->line($this->completedOrder(), $shirt, $medium);
        $mugLine = $this->line($this->completedOrder(), $mug);

        // Another size of the ordered shirt.
        $this->assertRejected('replacement_variant_id', fn () => $this->exchange($shirtLine, $hoodie, $blackMedium, extra: ['replacement_variant_id' => $large->id]));
        // A variant of a different product.
        $this->assertRejected('replacement_variant_id', fn () => $this->exchange($shirtLine, $hoodie, $blackMedium, extra: ['replacement_variant_id' => $blackMedium->id]));
        // A variant that does not exist.
        $this->assertRejected('replacement_variant_id', fn () => $this->exchange($shirtLine, $hoodie, $blackMedium, extra: ['replacement_variant_id' => 999999]));
        // A different product.
        $this->assertRejected('replacement_product_id', fn () => $this->exchange($mugLine, $hoodie, $blackMedium, extra: ['replacement_product_id' => $hoodie->id]));
        // A variant, for a product bought without one.
        $this->assertRejected('replacement_variant_id', fn () => $this->exchange($mugLine, $hoodie, $blackMedium, extra: ['replacement_variant_id' => $large->id]));

        $this->assertSame(0, OrderItemResolution::count());
    }

    public static function reasonsUbapDoesNotAccept(): array
    {
        return [
            // Not UBAP's error, so not taken back at all.
            'the customer chose the wrong variant' => ['customer_error'],
            'change of mind' => ['change_of_mind'],
            'no reason given' => [null],
        ];
    }

    #[DataProvider('reasonsUbapDoesNotAccept')]
    public function test_an_exchange_needs_a_reason_ubap_accepts(?string $reason): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $this->assertRejected('reason', fn () => $this->exchange($line, $shirt, $large, 'damaged', extra: ['reason' => $reason]));
        $this->assertRejected('reason', fn () => $this->exchangeFaultyItem($line, extra: ['reason' => $reason]));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public static function everyReasonForEveryOutcome(): array
    {
        return [
            'refund for defective' => [OrderItemResolutionType::Refund, 'defective'],
            'refund for damaged' => [OrderItemResolutionType::Refund, 'damaged'],
            'refund for seller error' => [OrderItemResolutionType::Refund, 'seller_error'],
            'exchange for defective' => [OrderItemResolutionType::Exchange, 'defective'],
            'exchange for damaged' => [OrderItemResolutionType::Exchange, 'damaged'],
            'exchange for seller error' => [OrderItemResolutionType::Exchange, 'seller_error'],
            'exchange for damaged, as the form hands it over' => [OrderItemResolutionType::Exchange, OrderItemResolutionReason::Damaged],
            'exchange for seller error, as the form hands it over' => [OrderItemResolutionType::Exchange, OrderItemResolutionReason::SellerError],
        ];
    }

    #[DataProvider('everyReasonForEveryOutcome')]
    public function test_every_reason_is_accepted_for_a_refund_and_for_an_exchange(OrderItemResolutionType $type, OrderItemResolutionReason|string $reason): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $expected = $reason instanceof OrderItemResolutionReason ? $reason : OrderItemResolutionReason::from($reason);

        $resolution = match (true) {
            $type === OrderItemResolutionType::Refund => $this->refund($line, 350, extra: ['reason' => $reason]),
            $expected === OrderItemResolutionReason::SellerError => $this->exchange($line, $shirt, $large, extra: ['reason' => $reason]),
            default => $this->exchangeFaultyItem($line, extra: ['reason' => $reason]),
        };

        $resolution = $resolution->fresh();
        $this->assertTrue($resolution->orderItem->is($line));
        $this->assertSame($type, $resolution->type);
        $this->assertSame($expected, $resolution->reason);
        $this->assertSame(1, OrderItemResolution::count());
    }

    public static function faultyItemReasons(): array
    {
        return [
            'defective' => ['defective'],
            'damaged' => ['damaged'],
        ];
    }

    #[DataProvider('faultyItemReasons')]
    public function test_a_defective_or_damaged_variant_is_exchanged_from_that_variants_stock(string $reason): void
    {
        $admin = $this->recordingAdmin();
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $order = $this->completedOrder();
        $line = $this->line($order, $shirt, $medium, quantity: 3);
        $lineBefore = $line->fresh()->getAttributes();

        $resolution = $this->exchangeFaultyItem($line, $reason, quantity: 2)->fresh();

        $this->assertSame(OrderItemResolutionType::Exchange, $resolution->type);
        $this->assertSame(OrderItemResolutionReason::from($reason), $resolution->reason);
        $this->assertSame(2, $resolution->quantity);
        // The replacement is the ordered item, taken from the line.
        $this->assertSame($shirt->id, $resolution->replacement_product_id);
        $this->assertSame($medium->id, $resolution->replacement_variant_id);
        $this->assertSame('Green / Medium', $resolution->replacement_label);
        // Nothing was released in error.
        $this->assertNull($resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_variant_id);
        $this->assertNull($resolution->incorrect_product_name);
        $this->assertNull($resolution->incorrect_variant_name);
        $this->assertNull($resolution->incorrect_item_label);
        $this->assertNull($resolution->incorrect_item_condition);
        $this->assertNull($resolution->refund_amount);
        $this->assertSame($admin->id, $resolution->processed_by);

        // Two more Mediums left the shelf, and the two returned are not put back.
        $this->assertSame(3, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
        $this->assertSame(0, $shirt->fresh()->stock_quantity);

        $this->assertSame($lineBefore, $line->fresh()->getAttributes());
        $this->assertSame('completed', $order->fresh()->status);
    }

    #[DataProvider('faultyItemReasons')]
    public function test_a_defective_or_damaged_product_sold_without_variants_is_exchanged_from_its_own_stock(string $reason): void
    {
        $mug = $this->product(['name' => 'CLSU Mug', 'price' => 180, 'stock_quantity' => 6]);
        $line = $this->line($this->completedOrder(), $mug, quantity: 2);

        $resolution = $this->exchangeFaultyItem($line, $reason, quantity: 2)->fresh();

        $this->assertSame($mug->id, $resolution->replacement_product_id);
        $this->assertNull($resolution->replacement_variant_id);
        $this->assertSame('CLSU Mug', $resolution->replacement_label);
        $this->assertNull($resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_item_condition);
        $this->assertSame(4, $mug->fresh()->stock_quantity);
    }

    public function test_a_defective_or_damaged_exchange_neither_needs_nor_records_an_item_released_in_error(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        // Nothing about an item released in error is asked for...
        $this->exchangeFaultyItem($line, 'defective');

        // ...and anything given for one is not recorded and moves no stock.
        $resolution = $this->exchangeFaultyItem($line, 'damaged', extra: [
            'incorrect_product_id' => $shirt->id,
            'incorrect_variant_id' => $large->id,
            'incorrect_item_condition' => 'damaged',
        ])->fresh();

        $this->assertNull($resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_variant_id);
        $this->assertNull($resolution->incorrect_product_name);
        $this->assertNull($resolution->incorrect_variant_name);
        $this->assertNull($resolution->incorrect_item_condition);

        $this->assertSame(2, OrderItemResolution::count());
        $this->assertSame(3, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    #[DataProvider('faultyItemReasons')]
    public function test_a_defective_or_damaged_exchange_needs_the_replacement_in_stock(string $reason): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 1, largeStock: 4);
        $mug = $this->product(['name' => 'CLSU Mug', 'stock_quantity' => 0]);
        $order = $this->completedOrder();
        $shirtLine = $this->line($order, $shirt, $medium, quantity: 2);
        $mugLine = $this->line($order, $mug);

        // One Medium left, and two to hand over.
        $this->assertRejected('quantity', fn () => $this->exchangeFaultyItem($shirtLine, $reason, quantity: 2));
        // No mugs left at all.
        $this->assertRejected('quantity', fn () => $this->exchangeFaultyItem($mugLine, $reason));

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(1, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
        $this->assertSame(0, $mug->fresh()->stock_quantity);

        // What is in stock can still be handed over.
        $this->exchangeFaultyItem($shirtLine, $reason, quantity: 1);
        $this->assertSame(0, $medium->fresh()->stock_quantity);
    }

    public function test_a_defective_or_damaged_exchange_is_held_to_the_units_left_on_the_line(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium, quantity: 2);

        $this->assertRejected('quantity', fn () => $this->exchangeFaultyItem($line, quantity: 0));
        $this->assertRejected('quantity', fn () => $this->exchangeFaultyItem($line, quantity: 3));

        $this->refund($line, 350);
        $this->assertRejected('quantity', fn () => $this->exchangeFaultyItem($line, 'damaged', quantity: 2));
        $this->assertSame(5, $medium->fresh()->stock_quantity);

        $this->exchangeFaultyItem($line, 'damaged', quantity: 1);
        $this->assertSame(4, $medium->fresh()->stock_quantity);
    }

    public function test_a_failure_while_saving_a_defective_or_damaged_exchange_rolls_the_replacement_stock_back(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $this->recordingAdmin();

        // Fail after the stock has been taken and the row inserted.
        Event::listen('eloquent.created: '.OrderItemResolution::class, function (): void {
            throw new RuntimeException('Simulated failure after the insert.');
        });

        $thrown = null;

        try {
            $this->exchangeFaultyItem($line, 'defective');
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertSame('Simulated failure after the insert.', $thrown?->getMessage());
        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
    }

    public function test_a_failure_while_saving_the_record_rolls_the_stock_back(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $this->recordingAdmin();

        // Fail after both writes have gone out, so anything short of one
        // transaction around them would leave the stock taken and the row saved.
        Event::listen('eloquent.created: '.OrderItemResolution::class, function (): void {
            throw new RuntimeException('Simulated failure after the insert.');
        });

        $thrown = null;

        try {
            $this->exchange($line, $shirt, $large, 'damaged');
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertSame('Simulated failure after the insert.', $thrown?->getMessage());
        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(4, $large->fresh()->stock_quantity);
        $this->assertSame(5, $medium->fresh()->stock_quantity);
    }

    public static function orderedVariantWithdrawals(): array
    {
        return [
            'deactivated' => [fn (ProductVariant $variant) => $variant->update(['is_active' => false])],
            'deleted' => [fn (ProductVariant $variant) => $variant->delete()],
        ];
    }

    #[DataProvider('orderedVariantWithdrawals')]
    public function test_a_line_whose_ordered_variant_is_no_longer_sold_can_be_refunded_but_not_exchanged(Closure $withdraw): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $withdraw($medium);

        $this->assertRejected('order_item_id', fn () => $this->exchange($line, $shirt, $large, 'damaged'));
        $this->assertSame(4, $large->fresh()->stock_quantity);

        $this->refund($line, 350);
        $this->assertSame(1, $line->resolutions()->count());
    }

    public function test_a_product_no_longer_sold_the_way_it_was_bought_cannot_be_exchanged(): void
    {
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);

        // Bought as Medium, but the shirt has since become a single-size product.
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $shirtLine = $this->line($this->completedOrder(), $shirt, $medium);
        $shirt->update(['has_variants' => false, 'stock_quantity' => 8]);

        $this->assertRejected('order_item_id', fn () => $this->exchange($shirtLine, $tumbler, condition: 'damaged'));
        $this->assertSame(8, $shirt->fresh()->stock_quantity);

        // Bought as a plain mug, but the mug is now sold by variant.
        $mug = $this->product(['stock_quantity' => 6]);
        $mugLine = $this->line($this->completedOrder(), $mug);
        $mug->update(['has_variants' => true]);
        ProductVariant::factory()->for($mug)->create(['stock_quantity' => 3, 'is_active' => true]);

        $this->assertRejected('order_item_id', fn () => $this->exchange($mugLine, $tumbler, condition: 'damaged'));

        $this->assertSame(9, $tumbler->fresh()->stock_quantity);
        $this->assertSame(0, OrderItemResolution::count());
    }

    public static function withdrawals(): array
    {
        return [
            'deleted from the catalogue' => [fn (Product $product) => $product->delete()],
            'deactivated' => [fn (Product $product) => $product->update(['is_active' => false])],
        ];
    }

    #[DataProvider('withdrawals')]
    public function test_a_line_whose_product_is_no_longer_sold_can_be_refunded_but_not_exchanged(Closure $withdraw): void
    {
        $mug = $this->product(['price' => 180, 'stock_quantity' => 6]);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $mug);
        $withdraw($mug);

        $this->assertRejected('order_item_id', fn () => $this->exchange($line, $tumbler, condition: 'damaged'));
        $this->assertSame(6, Product::withTrashed()->find($mug->id)->stock_quantity);
        $this->assertSame(9, $tumbler->fresh()->stock_quantity);

        $this->refund($line, 180);
        $this->assertSame(1, $line->resolutions()->count());
    }

    public function test_a_refund_cannot_exceed_what_was_paid_for_the_refunded_units(): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 350]), quantity: 2);

        $this->assertRejected('refund_amount', fn () => $this->refund($line, 350.01, quantity: 1));
        $this->assertSame(0, OrderItemResolution::count());

        $this->refund($line, 350, quantity: 1);
        $this->assertSame(1, OrderItemResolution::count());
    }

    public static function invalidRefundInput(): array
    {
        return [
            'negative amount' => ['refund_amount', ['refund_amount' => -1]],
            'non-numeric amount' => ['refund_amount', ['refund_amount' => 'a lot']],
            'missing amount' => ['refund_amount', ['refund_amount' => null]],
            'unapproved reason' => ['reason', ['reason' => 'change_of_mind']],
            'missing reason' => ['reason', ['reason' => null]],
            'zero quantity' => ['quantity', ['quantity' => 0]],
            'fractional quantity' => ['quantity', ['quantity' => 1.5]],
            'unparseable date' => ['processed_at', ['processed_at' => 'last week-ish']],
            'future date' => ['processed_at', ['processed_at' => '2026-09-14']],
            'date before the order was collected' => ['processed_at', ['processed_at' => '2026-09-10']],
        ];
    }

    #[DataProvider('invalidRefundInput')]
    public function test_invalid_refund_input_is_rejected(string $field, array $override): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 180]));

        $this->assertRejected($field, fn () => $this->resolutions()->recordRefund($line, $this->recordingAdmin(), array_merge([
            'reason' => 'defective',
            'quantity' => 1,
            'refund_amount' => 180,
            'processed_at' => '2026-09-13',
        ], $override)));

        $this->assertSame(0, OrderItemResolution::count());
    }

    public function test_a_line_cannot_be_refunded_twice(): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 180]));
        $this->refund($line, 180);

        $this->assertRejected('quantity', fn () => $this->refund($line, 180));

        $this->assertSame(1, OrderItemResolution::count());
    }

    public function test_refunds_share_out_the_units_that_were_bought(): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 100]), quantity: 3);

        $this->refund($line, 200, quantity: 2);
        $this->assertRejected('quantity', fn () => $this->refund($line, 200, quantity: 2));
        $this->refund($line, 100, quantity: 1);

        $this->assertSame(3, (int) $line->resolutions()->sum('quantity'));
    }

    public function test_a_refunded_unit_cannot_then_be_exchanged(): void
    {
        $mug = $this->product(['price' => 180, 'stock_quantity' => 6]);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $mug);
        $this->refund($line, 180);

        $this->assertRejected('quantity', fn () => $this->exchange($line, $tumbler, condition: 'damaged'));

        $this->assertSame(6, $mug->fresh()->stock_quantity);
        $this->assertSame(9, $tumbler->fresh()->stock_quantity);
    }

    public function test_an_exchange_cannot_cover_more_units_than_the_line_has_left(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium, quantity: 2);

        $this->assertRejected('quantity', fn () => $this->exchange($line, $shirt, $large, 'damaged', quantity: 3));

        $this->refund($line, 350);
        $this->assertRejected('quantity', fn () => $this->exchange($line, $shirt, $large, 'damaged', quantity: 2));
        $this->assertSame(4, $large->fresh()->stock_quantity);

        $this->exchange($line, $shirt, $large, 'damaged', quantity: 1);
        $this->assertSame(3, $large->fresh()->stock_quantity);
    }

    public function test_a_line_can_be_resolved_again_after_an_exchange(): void
    {
        [$shirt, $medium, $large] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        $line = $this->line($this->completedOrder(), $shirt, $medium);

        $this->exchange($line, $shirt, $large);
        // The wrong size was handed over a second time.
        $this->exchange($line, $shirt, $large);

        // And the Medium that finally reached the customer turned out defective.
        $this->refund($line, 350);
        $this->assertRejected('quantity', fn () => $this->exchange($line, $shirt, $large));

        $this->assertSame(3, $line->resolutions()->count());
        $this->assertSame(5, $medium->fresh()->stock_quantity);
        $this->assertSame(4, $large->fresh()->stock_quantity);
    }

    public static function uncollectedStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'processing' => ['processing'],
            'ready for pickup' => ['ready_for_pickup'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('uncollectedStatuses')]
    public function test_only_collected_orders_accept_a_resolution(string $status): void
    {
        $line = $this->line($this->completedOrder(['status' => $status]), $this->product(['price' => 180]));

        $this->assertRejected('order_item_id', fn () => $this->refund($line, 180));

        $this->assertSame(0, OrderItemResolution::count());
    }

    public function test_a_deleted_order_does_not_accept_a_resolution(): void
    {
        $order = $this->completedOrder();
        $line = $this->line($order, $this->product(['price' => 180]));
        $order->delete();

        $this->assertRejected('order_item_id', fn () => $this->refund($line, 180));
    }

    public function test_an_admin_without_the_record_resolution_permission_is_refused(): void
    {
        $mug = $this->product(['price' => 180, 'stock_quantity' => 6]);
        $tumbler = $this->product(['name' => 'CLSU Tumbler', 'stock_quantity' => 9]);
        $line = $this->line($this->completedOrder(), $mug);
        $orderEditor = $this->adminWithPermissions(['ViewAny:Order', 'View:Order', 'Update:Order']);

        $this->assertThrows(fn () => $this->resolutions()->recordExchange($line, $orderEditor, [
            'reason' => 'seller_error',
            'quantity' => 1,
            'incorrect_product_id' => $tumbler->id,
            'incorrect_item_condition' => 'damaged',
            'processed_at' => '2026-09-13',
        ]), AuthorizationException::class);

        $this->assertSame(0, OrderItemResolution::count());
        $this->assertSame(6, $mug->fresh()->stock_quantity);
        $this->assertSame(9, $tumbler->fresh()->stock_quantity);
    }

    public function test_the_record_still_reads_after_both_items_leave_the_catalogue(): void
    {
        [$shirt, $medium] = $this->shirtWithSizes(mediumStock: 5, largeStock: 4);
        [$hoodie, $blackMedium] = $this->hoodieInBlackMedium();
        $line = $this->line($this->completedOrder(), $shirt, $medium);
        $resolution = $this->exchange($line, $hoodie, $blackMedium, 'defective');

        // Each takes its variants with it; the shirt also nulls the line's own
        // product link.
        $shirt->forceDelete();
        $hoodie->forceDelete();

        $resolution = OrderItemResolution::find($resolution->id);
        $this->assertNotNull($resolution, 'Removing catalogue rows must not take the history with it.');
        $this->assertNull($resolution->replacement_product_id);
        $this->assertNull($resolution->replacement_variant_id);
        $this->assertNull($resolution->incorrect_product_id);
        $this->assertNull($resolution->incorrect_variant_id);
        $this->assertSame('Green / Medium', $resolution->replacement_label);
        $this->assertSame('CLSU Hoodie — Black / Medium', $resolution->incorrect_item_label);
        $this->assertSame(ReturnedItemCondition::Defective, $resolution->incorrect_item_condition);
    }

    public function test_the_processing_admin_can_be_removed_without_losing_the_record(): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 180]));
        $resolution = $this->refund($line, 180);

        $this->recordingAdmin()->delete();

        $this->assertNull($resolution->fresh()->processed_by);
    }

    public function test_a_recorded_resolution_cannot_be_edited_or_deleted(): void
    {
        $line = $this->line($this->completedOrder(), $this->product(['price' => 180]));
        $resolution = $this->refund($line, 180);

        $this->assertThrows(fn () => $resolution->update(['refund_amount' => 1]), LogicException::class);
        $this->assertThrows(fn () => $resolution->delete(), LogicException::class);

        $this->assertSame('180.00', $resolution->fresh()->refund_amount);
    }
}
