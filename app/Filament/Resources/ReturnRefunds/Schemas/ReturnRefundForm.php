<?php

namespace App\Filament\Resources\ReturnRefunds\Schemas;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Enums\ReturnedItemCondition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Records what UBAP already did about an order line: a refund, or an exchange
 * for the item that was ordered, for any reason UBAP accepts a return for.
 * This only collects the details; ReturnRefundResolutionService decides whether
 * they hold up and does the writing -- see CreateReturnRefund.
 */
class ReturnRefundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order item')
                ->description('A refund or exchange can only be recorded for an order that was collected.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    static::orderSelect(),
                    static::itemSelect(),
                ]),

            Section::make('Outcome')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    static::typeToggle(),
                    static::reasonSelect(),
                    static::quantityInput(),
                    static::refundAmountInput(),
                    static::replacementEntry(),
                    static::incorrectProductSelect(),
                    static::incorrectVariantSelect(),
                    static::incorrectItemConditionSelect(),
                ]),

            Section::make('Processing')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    static::processedAtPicker(),
                    static::recordedByEntry(),
                    static::notesInput(),
                ]),
        ]);
    }

    /**
     * Collected orders, found by order number or customer name.
     */
    private static function orderSelect(): Select
    {
        return Select::make('order_id')
            ->label('Order')
            ->placeholder('Search by order number or customer')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Order::query()
                ->with('customer')
                ->whereIn('status', Order::RESOLVABLE_STATUSES)
                ->where(fn ($query) => $query
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")))
                ->latest()
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Order $order): array => [$order->id => static::orderLabel($order)])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => ($order = Order::with('customer')->find($value))
                ? static::orderLabel($order)
                : null)
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('order_item_id', null));
    }

    /**
     * Only lines with units left to settle are offered.
     */
    private static function itemSelect(): Select
    {
        return Select::make('order_item_id')
            ->label('Order item')
            ->options(fn (Get $get): array => filled($get('order_id'))
                ? OrderItem::query()
                    ->with('resolutions')
                    ->where('order_id', (int) $get('order_id'))
                    ->get()
                    ->filter(fn (OrderItem $item): bool => $item->resolvableQuantity() > 0)
                    ->mapWithKeys(fn (OrderItem $item): array => [$item->id => static::itemLabel($item)])
                    ->all()
                : [])
            ->helperText(fn (Get $get): ?string => blank($get('order_id')) ? 'Choose the order first.' : null)
            ->required()
            ->live();
    }

    private static function typeToggle(): ToggleButtons
    {
        return ToggleButtons::make('type')
            ->label('Outcome')
            ->options(OrderItemResolutionType::class)
            ->inline()
            ->required()
            ->live()
            ->columnSpanFull();
    }

    /**
     * Every reason can be refunded or exchanged, so all of them are offered
     * whatever the outcome, and changing the outcome leaves the reason alone.
     */
    private static function reasonSelect(): Select
    {
        return Select::make('reason')
            ->label('Reason')
            ->options(collect(OrderItemResolutionReason::cases())
                ->mapWithKeys(fn (OrderItemResolutionReason $reason): array => [$reason->value => $reason->getLabel()])
                ->all())
            ->required()
            // An exchange for seller error also asks after the item released in error.
            ->live();
    }

    private static function quantityInput(): TextInput
    {
        return TextInput::make('quantity')
            ->label('Order Quantity')
            ->integer()
            ->minValue(1)
            ->maxValue(fn (Get $get): ?int => static::selectedItem($get)?->resolvableQuantity())
            ->default(1)
            ->required()
            ->live(onBlur: true)
            ->helperText(fn (Get $get): ?string => ($item = static::selectedItem($get))
                ? $item->resolvableQuantity()." of {$item->quantity} unit(s) on this line can still be refunded or exchanged."
                : null);
    }

    private static function refundAmountInput(): TextInput
    {
        return TextInput::make('refund_amount')
            ->label('Refund amount')
            ->numeric()
            ->prefix('₱')
            ->minValue(0)
            ->required()
            ->visible(fn (Get $get): bool => static::isRefund($get))
            ->helperText(function (Get $get): ?string {
                $item = static::selectedItem($get);

                if (! $item) {
                    return 'The order line stays exactly as it was sold.';
                }

                $quantity = max(1, (int) $get('quantity'));

                return 'At most ₱'.number_format((float) $item->price * $quantity, 2)." for {$quantity} unit(s).";
            });
    }

    /**
     * Not a choice: the customer gets exactly the product and variant the line
     * was for. The reason decides whether handing it over takes stock.
     */
    private static function replacementEntry(): TextEntry
    {
        return TextEntry::make('replacement')
            ->label('Replacement')
            ->state(fn (Get $get): ?string => ($item = static::selectedItem($get))
                ? collect([$item->product_name, $item->variant_name])->filter()->implode(' — ')
                : null)
            ->placeholder('Choose an order item')
            ->helperText(fn (Get $get): string => match (static::reason($get)) {
                null => 'Always the item that was ordered, so it is not chosen here.',
                OrderItemResolutionReason::SellerError => 'Always the item that was ordered, so it is not chosen here. It came out of stock at checkout, so it is not taken out again.',
                default => 'Always the item that was ordered, so it is not chosen here. Another unit is taken out of its stock, and the one returned is not put back.',
            })
            ->visible(fn (Get $get): bool => static::isExchange($get));
    }

    /**
     * What UBAP handed over by mistake, for an exchange because of seller
     * error: another variant of the ordered product, or a different product
     * altogether. No other reason has an item released in error to name.
     */
    private static function incorrectProductSelect(): Select
    {
        return Select::make('incorrect_product_id')
            ->label('Item released in error')
            ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('incorrect_variant_id', null))
            ->visible(fn (Get $get): bool => static::isSellerErrorExchange($get));
    }

    private static function incorrectVariantSelect(): Select
    {
        return Select::make('incorrect_variant_id')
            ->label('Variant released in error')
            ->options(fn (Get $get): array => ProductVariant::query()
                ->where('product_id', (int) $get('incorrect_product_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->visible(fn (Get $get): bool => static::isSellerErrorExchange($get)
                && (bool) Product::find((int) $get('incorrect_product_id'))?->has_variants)
            ->required();
    }

    private static function incorrectItemConditionSelect(): Select
    {
        return Select::make('incorrect_item_condition')
            ->label('Condition when returned')
            ->options(ReturnedItemCondition::class)
            ->required()
            ->helperText('Sellable goes back on the shelf with no stock change. Damaged or defective is written off that item\'s stock.')
            ->visible(fn (Get $get): bool => static::isSellerErrorExchange($get));
    }

    private static function processedAtPicker(): DatePicker
    {
        return DatePicker::make('processed_at')
            ->label('Processed date')
            ->helperText('The day UBAP gave the refund or handed over the replacement.')
            ->default(fn (): string => today()->toDateString())
            ->maxDate(fn () => today())
            ->required();
    }

    /** Always whoever is recording it. */
    private static function recordedByEntry(): TextEntry
    {
        return TextEntry::make('processed_by_name')
            ->label('Processed by')
            ->state(fn (): ?string => auth()->user()?->name);
    }

    private static function notesInput(): Textarea
    {
        return Textarea::make('notes')
            ->label('Notes')
            ->helperText('For administrators only. Customers do not see these notes.')
            ->rows(3)
            ->maxLength(2000)
            ->columnSpanFull();
    }

    private static function outcome(Get $get): ?OrderItemResolutionType
    {
        $type = $get('type');

        return $type instanceof OrderItemResolutionType ? $type : OrderItemResolutionType::tryFrom((string) $type);
    }

    private static function isRefund(Get $get): bool
    {
        return static::outcome($get) === OrderItemResolutionType::Refund;
    }

    private static function isExchange(Get $get): bool
    {
        return static::outcome($get) === OrderItemResolutionType::Exchange;
    }

    private static function reason(Get $get): ?OrderItemResolutionReason
    {
        $reason = $get('reason');

        return $reason instanceof OrderItemResolutionReason ? $reason : OrderItemResolutionReason::tryFrom((string) $reason);
    }

    /** The one kind of exchange that has an item released in error to record. */
    private static function isSellerErrorExchange(Get $get): bool
    {
        return static::isExchange($get) && static::reason($get) === OrderItemResolutionReason::SellerError;
    }

    private static function selectedItem(Get $get): ?OrderItem
    {
        if (blank($get('order_id')) || blank($get('order_item_id'))) {
            return null;
        }

        return OrderItem::query()
            ->with('resolutions')
            ->where('order_id', (int) $get('order_id'))
            ->find((int) $get('order_item_id'));
    }

    private static function orderLabel(Order $order): string
    {
        return collect([$order->order_number, $order->customer?->name])->filter()->implode(' — ');
    }

    private static function itemLabel(OrderItem $item): string
    {
        return collect([$item->product_name, $item->variant_name])->filter()->implode(' — ')
            ." (×{$item->quantity}, ₱".number_format((float) $item->price, 2).' each)';
    }
}
