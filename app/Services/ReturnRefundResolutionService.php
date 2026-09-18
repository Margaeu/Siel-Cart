<?php

namespace App\Services;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Enums\ReturnedItemCondition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRefundResolution;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records what UBAP decided about an order line after the sale.
 *
 * The decision is made and carried out outside the shop. By the time it
 * reaches here the money has been handed back or the replacement handed over,
 * so all this does is check the record holds up, keep stock honest for an
 * exchange, and write it -- together or not at all.
 *
 * Anything that does not hold up is thrown as a ValidationException keyed by
 * the input field responsible, so a form can point at the right field.
 */
class ReturnRefundResolutionService
{
    public function recordRefund(OrderItem $item, User $processor, array $data): ReturnRefundResolution
    {
        return $this->record(OrderItemResolutionType::Refund, $item, $processor, $data);
    }

    /**
     * UBAP exchanges an item for any reason it accepts a return for: it was
     * defective, it was damaged, or UBAP released the wrong one -- the customer
     * ordered Black / Medium and was handed Black / Large, or was handed a
     * different product altogether. Whatever the reason, the customer then gets
     * exactly what the line records was ordered; that is never chosen here.
     *
     * An item the customer chose wrong themselves, or no longer wants, was not
     * UBAP's error and is not exchanged.
     *
     * Only an exchange for seller error names the item released in error and
     * the condition it came back in. The reason decides what happens to stock
     * -- see exchangeAttributes().
     */
    public function recordExchange(OrderItem $item, User $processor, array $data): ReturnRefundResolution
    {
        return $this->record(OrderItemResolutionType::Exchange, $item, $processor, $data);
    }

    private function record(OrderItemResolutionType $type, OrderItem $item, User $processor, array $data): ReturnRefundResolution
    {
        Gate::forUser($processor)->authorize('recordResolution', $item->order()->withTrashed()->firstOrFail());

        $input = $this->validateInput($type, $data);

        return DB::transaction(function () use ($type, $item, $processor, $input): ReturnRefundResolution {
            // Order, line, product, variant: the same order restoreStock() and
            // checkout take their locks in, so none of them deadlock this.
            // Everything below is decided on rows read under these locks.
            $order = Order::query()->whereKey($item->order_id)->lockForUpdate()->first();

            if (! $order?->canRecordItemResolutions()) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'A refund or exchange can only be recorded for an order that was collected.',
                ]);
            }

            $item = OrderItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureUnitsAreAvailable($item, $input['quantity']);
            $this->ensureProcessedAfterCollection($order, $input['processed_at']);

            $outcome = $type === OrderItemResolutionType::Refund
                ? $this->refundAttributes($item, $input)
                : $this->exchangeAttributes($item, $input);

            return ReturnRefundResolution::create([
                'order_item_id' => $item->id,
                'type' => $type,
                'reason' => $input['reason'],
                'quantity' => $input['quantity'],
                'notes' => $input['notes'],
                'processed_by' => $processor->id,
                'processed_at' => $input['processed_at'],
                ...$outcome,
            ]);
        });
    }

    /**
     * @return array{reason: OrderItemResolutionReason, quantity: int, notes: ?string, processed_at: Carbon, refund_amount: mixed, replacement_product_id: ?int, replacement_variant_id: ?int, incorrect_product_id: ?int, incorrect_variant_id: ?int, incorrect_item_condition: ?ReturnedItemCondition}
     */
    private function validateInput(OrderItemResolutionType $type, array $data): array
    {
        $rules = [
            'reason' => ['required', Rule::enum(OrderItemResolutionReason::class)],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // The day the decision was carried out, so never a day to come.
            'processed_at' => ['required', 'date', 'before:tomorrow'],
        ];

        $rules += $type === OrderItemResolutionType::Refund
            ? ['refund_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2']]
            : [
                // Never a choice -- the replacement is what the line was for.
                // Accepted only so that asking for anything else is refused.
                'replacement_product_id' => ['nullable', 'integer'],
                'replacement_variant_id' => ['nullable', 'integer'],
            ];

        $messages = [];

        // Only an exchange for seller error has an item released in error to
        // name. Every other record leaves these out of what is validated, so
        // they are never recorded for it.
        if ($type === OrderItemResolutionType::Exchange && $this->givenReason($data) === OrderItemResolutionReason::SellerError) {
            $rules += [
                // What UBAP handed over by mistake, and how it came back.
                'incorrect_product_id' => ['required', 'integer'],
                'incorrect_variant_id' => ['nullable', 'integer'],
                'incorrect_item_condition' => ['required', Rule::enum(ReturnedItemCondition::class)],
            ];

            $messages['incorrect_product_id.required'] = 'Choose the item UBAP released in error.';
            $messages['incorrect_item_condition.required'] = 'Choose the condition the item released in error came back in.';
        }

        $validated = Validator::make($data, $rules, $messages)->validate();

        // A Filament select bound to an enum hands over the case itself.
        $condition = $validated['incorrect_item_condition'] ?? null;

        return [
            'reason' => $validated['reason'] instanceof OrderItemResolutionReason
                ? $validated['reason']
                : OrderItemResolutionReason::from($validated['reason']),
            'quantity' => (int) $validated['quantity'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'processed_at' => Carbon::parse($validated['processed_at']),
            'refund_amount' => $validated['refund_amount'] ?? null,
            'replacement_product_id' => filled($validated['replacement_product_id'] ?? null)
                ? (int) $validated['replacement_product_id']
                : null,
            'replacement_variant_id' => filled($validated['replacement_variant_id'] ?? null)
                ? (int) $validated['replacement_variant_id']
                : null,
            'incorrect_product_id' => filled($validated['incorrect_product_id'] ?? null)
                ? (int) $validated['incorrect_product_id']
                : null,
            'incorrect_variant_id' => filled($validated['incorrect_variant_id'] ?? null)
                ? (int) $validated['incorrect_variant_id']
                : null,
            'incorrect_item_condition' => $condition === null || $condition instanceof ReturnedItemCondition
                ? $condition
                : ReturnedItemCondition::from($condition),
        ];
    }

    /**
     * The reason as handed over, read before validation because it decides
     * what else an exchange has to give. Null for anything that is not a
     * reason UBAP accepts, which validation then refuses.
     */
    private function givenReason(array $data): ?OrderItemResolutionReason
    {
        $reason = $data['reason'] ?? null;

        if ($reason instanceof OrderItemResolutionReason) {
            return $reason;
        }

        return is_string($reason) ? OrderItemResolutionReason::tryFrom($reason) : null;
    }

    private function ensureUnitsAreAvailable(OrderItem $item, int $quantity): void
    {
        $available = $item->resolvableQuantity();

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => $available === 0
                    ? 'Every unit on this line has already been refunded.'
                    : "Only {$available} unit(s) on this line can still be refunded or exchanged.",
            ]);
        }
    }

    private function ensureProcessedAfterCollection(Order $order, Carbon $processedAt): void
    {
        // Orders completed before completed_at was being saved have none, and
        // there is nothing to hold those against.
        if (! $order->completed_at) {
            return;
        }

        if ($processedAt->copy()->startOfDay()->lt($order->completed_at->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'processed_at' => 'The processed date cannot be before the order was collected on '
                    .$order->completed_at->format('M d, Y').'.',
            ]);
        }
    }

    private function refundAttributes(OrderItem $item, array $input): array
    {
        // What was paid for the units being refunded. Earlier refunds took
        // their own units with them, so this and the unit check together keep
        // everything refunded on a line within what the line cost.
        $refundable = round((float) $item->price * $input['quantity'], 2);

        if ((float) $input['refund_amount'] > $refundable) {
            throw ValidationException::withMessages([
                'refund_amount' => 'The refund cannot be more than the ₱'.number_format($refundable, 2)
                    ." paid for {$input['quantity']} unit(s).",
            ]);
        }

        return ['refund_amount' => $input['refund_amount']];
    }

    /**
     * The replacement is always another unit of what was ordered. Which stock
     * moves depends on why it is being exchanged.
     *
     * For seller error, stock only ever moves for the item released in error,
     * and only when it cannot be sold again. The ordered item came out of stock
     * at checkout and stayed on the shelf, held for this line, so handing it
     * over now changes nothing. The wrong item was never taken out of stock
     * when it was handed over, so one that comes back sellable is already
     * counted. One that comes back damaged or defective will not be sold, so it
     * is written off its stock.
     *
     * For a defective or damaged item, the customer was handed the ordered item
     * itself, and checkout already took that unit out of stock. The replacement
     * is one more unit leaving the shelf, so it comes out of stock now. The
     * unit that came back will not be sold, so it is not put back.
     */
    private function exchangeAttributes(OrderItem $item, array $input): array
    {
        // Products before variants, each by ascending id -- the order checkout
        // and restoreStock() lock them in, so none of them deadlock this.
        //
        // Trashed products are left out on purpose. A product taken out of the
        // catalogue is not being sold, so there is nothing to hand over from it.
        $products = Product::query()
            ->whereKey(array_filter([$item->product_id, $input['incorrect_product_id']]))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $product = $item->product_id ? $products->get($item->product_id) : null;

        if (! $product?->is_active) {
            throw ValidationException::withMessages([
                'order_item_id' => 'This product is no longer sold, so it cannot be exchanged. Record a refund instead.',
            ]);
        }

        $ordered = collect([$item->product_name, $item->variant_name])->filter()->implode(' — ');

        // Refused outright rather than quietly recorded as the right item, so
        // nobody reads the record as a swap to something else.
        if ($input['replacement_product_id'] && $input['replacement_product_id'] !== $product->id) {
            throw ValidationException::withMessages([
                'replacement_product_id' => "Only the item that was ordered, {$ordered}, can be handed over in an exchange.",
            ]);
        }

        if ($input['replacement_variant_id'] && $input['replacement_variant_id'] !== (int) $item->product_variant_id) {
            throw ValidationException::withMessages([
                'replacement_variant_id' => "Only the item that was ordered, {$ordered}, can be handed over in an exchange.",
            ]);
        }

        $variants = ProductVariant::query()
            ->whereKey(array_filter([$item->product_variant_id, $input['incorrect_variant_id']]))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

        if ($variant && (! $variant->is_active || (int) $variant->product_id !== $product->id)) {
            $variant = null;
        }

        // What was ordered has to still be sold the way it was bought: that
        // variant, or the product itself if it was bought without one. A
        // product that has since switched between the two no longer sells it.
        if ($product->has_variants ? ! $variant : (bool) $item->product_variant_id) {
            throw ValidationException::withMessages([
                'order_item_id' => "{$ordered} is no longer sold, so it cannot be exchanged. Record a refund instead.",
            ]);
        }

        $replacement = [
            'replacement_product_id' => $product->id,
            'replacement_variant_id' => $variant?->id,
            'replacement_product_name' => $product->name,
            'replacement_variant_name' => $variant?->name,
        ];

        if ($input['reason'] !== OrderItemResolutionReason::SellerError) {
            // A variable product's own stock column means nothing; its variants
            // each hold theirs.
            $stockHolder = $variant ?? $product;

            if ($stockHolder->stock_quantity < $input['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$stockHolder->stock_quantity} of {$ordered} in stock, so {$input['quantity']} cannot be handed over as a replacement. Record a refund instead, or correct its stock first.",
                ]);
            }

            $stockHolder->decrement('stock_quantity', $input['quantity']);

            // Nothing was released in error, so there is nothing else to name.
            return [
                ...$replacement,
                'incorrect_product_id' => null,
                'incorrect_variant_id' => null,
                'incorrect_product_name' => null,
                'incorrect_variant_name' => null,
                'incorrect_item_condition' => null,
            ];
        }

        $incorrectProduct = $products->get($input['incorrect_product_id']);

        if (! $incorrectProduct) {
            throw ValidationException::withMessages([
                'incorrect_product_id' => 'Choose the item UBAP released in error from the catalogue.',
            ]);
        }

        $incorrectVariant = null;

        if ($incorrectProduct->has_variants) {
            $incorrectVariant = $input['incorrect_variant_id'] ? $variants->get($input['incorrect_variant_id']) : null;

            if (! $incorrectVariant || (int) $incorrectVariant->product_id !== $incorrectProduct->id) {
                throw ValidationException::withMessages([
                    'incorrect_variant_id' => "Choose which variant of {$incorrectProduct->name} was released in error.",
                ]);
            }
        } elseif ($input['incorrect_variant_id']) {
            throw ValidationException::withMessages([
                'incorrect_variant_id' => "{$incorrectProduct->name} is sold without variants, so there is no variant to choose.",
            ]);
        }

        // Handing over the item that was ordered is no error to put right.
        if ($incorrectProduct->id === $product->id && $incorrectVariant?->id === $variant?->id) {
            throw ValidationException::withMessages([
                ($incorrectVariant ? 'incorrect_variant_id' : 'incorrect_product_id') => "{$ordered} is the item that was ordered, so it cannot be the item released in error.",
            ]);
        }

        if (! $input['incorrect_item_condition']->isSellable()) {
            // A variable product's own stock column means nothing; its variants
            // each hold theirs.
            $writeOff = $incorrectVariant ?? $incorrectProduct;

            if ($writeOff->stock_quantity < $input['quantity']) {
                $incorrect = collect([$incorrectProduct->name, $incorrectVariant?->name])->filter()->implode(' — ');

                throw ValidationException::withMessages([
                    'quantity' => "Only {$writeOff->stock_quantity} of {$incorrect} on record, so {$input['quantity']} cannot be written off. Correct its stock first.",
                ]);
            }

            $writeOff->decrement('stock_quantity', $input['quantity']);
        }

        return [
            ...$replacement,
            'incorrect_product_id' => $incorrectProduct->id,
            'incorrect_variant_id' => $incorrectVariant?->id,
            'incorrect_product_name' => $incorrectProduct->name,
            'incorrect_variant_name' => $incorrectVariant?->name,
            'incorrect_item_condition' => $input['incorrect_item_condition'],
        ];
    }
}
