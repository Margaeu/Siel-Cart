<?php

namespace App\Models;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Enums\ReturnedItemCondition;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The final outcome of a return, recorded against one order line.
 *
 * UBAP handles returns outside the shop -- by email or at the counter -- and
 * an admin records what was decided here once it has been carried out. This
 * is that record and nothing more. It does not run the return, and it never
 * changes the line, the order, or the order's status.
 *
 * Create these through ReturnRefundResolutionService, which validates them and
 * makes whatever stock change an exchange calls for in the same transaction.
 */
class ReturnRefundResolution extends Model
{
    protected $fillable = [
        'order_item_id',
        'type',
        'reason',
        'quantity',
        'refund_amount',
        'replacement_product_id',
        'replacement_variant_id',
        'replacement_product_name',
        'replacement_variant_name',
        'incorrect_product_id',
        'incorrect_variant_id',
        'incorrect_product_name',
        'incorrect_variant_name',
        'incorrect_item_condition',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'type' => OrderItemResolutionType::class,
            'reason' => OrderItemResolutionReason::class,
            'quantity' => 'integer',
            'refund_amount' => 'decimal:2',
            'replacement_product_id' => 'integer',
            'replacement_variant_id' => 'integer',
            'incorrect_product_id' => 'integer',
            'incorrect_variant_id' => 'integer',
            'incorrect_item_condition' => ReturnedItemCondition::class,
            'processed_by' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    // relationships
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** Deleted or not, for the reason OrderItem::product() gives. */
    public function replacementProduct()
    {
        return $this->belongsTo(Product::class, 'replacement_product_id')->withTrashed();
    }

    public function replacementVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'replacement_variant_id');
    }

    /** Deleted or not, for the reason OrderItem::product() gives. */
    public function incorrectProduct()
    {
        return $this->belongsTo(Product::class, 'incorrect_product_id')->withTrashed();
    }

    public function incorrectVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'incorrect_variant_id');
    }

    /**
     * What was handed over instead, read from the names copied when the
     * exchange was recorded so it survives the catalogue changing.
     *
     * The replacement is always another unit of the line's own product, so the
     * variant name alone says which. A product sold without variants has only
     * its own name to give.
     */
    public function getReplacementLabelAttribute(): ?string
    {
        return $this->replacement_variant_name ?? $this->replacement_product_name;
    }

    /**
     * What UBAP released in error, from the names copied when the exchange was
     * recorded. It can be a different product altogether, so the product name
     * always leads.
     */
    public function getIncorrectItemLabelAttribute(): ?string
    {
        return collect([$this->incorrect_product_name, $this->incorrect_variant_name])->filter()->implode(' — ') ?: null;
    }

    /**
     * A recorded outcome is history. Getting one wrong calls for a correction
     * workflow, which has not been defined, rather than an edit in place -- an
     * exchange may have written stock off that an edit could not account for.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('An order item resolution cannot be changed once it is recorded.');
        });

        static::deleting(function (): never {
            throw new LogicException('An order item resolution cannot be deleted once it is recorded.');
        });
    }
}
