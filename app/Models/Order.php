<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity, SoftDeletes;

    /**
     * Statuses that mean the order is no longer a sale, so its units belong
     * back on the shelf. Only a cancellation qualifies: the goods never left.
     */
    public const RESTOCKING_STATUSES = ['cancelled'];

    /**
     * Statuses whose goods were collected and paid for, so UBAP may since have
     * refunded or exchanged something from them.
     */
    public const RESOLVABLE_STATUSES = ['completed'];

    /**
     * Statuses of an order still in progress: not yet collected, not
     * cancelled. A customer with one of these cannot delete their account,
     * because UBAP would be left holding goods for nobody.
     */
    public const ACTIVE_STATUSES = ['pending', 'processing', 'ready_for_pickup'];

    /**
     * Statuses whose pickup schedule UBAP can move. A schedule only exists
     * once the order is ready to collect; before that there is nothing to move.
     */
    public const RESCHEDULABLE_STATUSES = ['ready_for_pickup'];

    /**
     * Every status history note written by reschedulePickup() starts with
     * this, which is how rescheduleHistories() tells those rows apart.
     */
    public const RESCHEDULE_NOTE_PREFIX = 'Pickup rescheduled';

    protected $fillable = [
        'order_number',
        'customer_id',
        'subtotal',
        'total',
        'pickup_date',
        'pickup_slot',
        'pickup_location',
        'claim_number',
        'payment_method',
        'payment_status',
        'status',
        'claimant_name',
        'claimant_phone',
        'or_number',
        'admin_notes',
        // These were being written by the cancel and complete flows without
        // being fillable, so mass assignment dropped them silently -- orders
        // ended up cancelled with no reason recorded, and completed with no
        // completed_at, which is the date the return window is measured from.
        'cancellation_reason',
        'cancelled_at',
        'completed_at',
        'original_pickup_date',
        'original_pickup_slot',
        'rescheduled_at',
        'reschedule_count',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'pickup_date' => 'date',
            'original_pickup_date' => 'date',
            'rescheduled_at' => 'datetime',
            'reschedule_count' => 'integer',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'order_number',
                'customer_id',
                'subtotal',
                'total',
                'pickup_date',
                'pickup_slot',
                'pickup_location',
                'claim_number',
                'payment_method',
                'payment_status',
                'status',
                'claimant_name',
                'claimant_phone',
                'or_number',
                'admin_notes',
                'cancellation_reason',
                'cancelled_at',
                'completed_at',
                'original_pickup_date',
                'original_pickup_slot',
                'rescheduled_at',
                'reschedule_count',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Scope to filter by status
     */
    #[Scope]
    protected function ofStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope to filter by payment status
     */
    #[Scope]
    protected function paymentStatus(Builder $query, string $status): void
    {
        $query->where('payment_status', $status);
    }

    /**
     * Scope to only pending orders
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Scope to only processing orders
     */
    #[Scope]
    protected function processing(Builder $query): void
    {
        $query->where('status', 'processing');
    }

    /**
     * Scope to only orders waiting to be claimed
     */
    #[Scope]
    protected function readyForPickup(Builder $query): void
    {
        $query->where('status', 'ready_for_pickup');
    }

    /**
     * Scope to only orders that have been claimed
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    /**
     * Orders with a refund or exchange recorded on any line
     */
    #[Scope]
    protected function withReturnActivity(Builder $query): void
    {
        $query->whereHas('items.resolutions');
    }

    // relationships
    // withTrashed(): a deleted customer is kept as an anonymized soft-deleted
    // row so their order history survives. Without it the relation is null and
    // every admin page reading customer.* breaks on those orders.
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function rescheduleHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)
            ->where('notes', 'like', self::RESCHEDULE_NOTE_PREFIX.'%')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    // helper methods
    public function updateStatus(string $newStatus, ?string $notes = null, ?int $userId = null, array $attributes = []): void
    {
        DB::transaction(function () use ($newStatus, $notes, $userId, $attributes) {
            $this->update(array_merge($attributes, [
                'status' => $newStatus,
            ]));

            $this->statusHistories()->create([
                'status' => $newStatus,
                'notes' => $notes,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Get the end of this order's scheduled pickup period.
     *
     * Pickup slots are stored as a display-friendly interval such as
     * "8:00 AM - 5:00 PM". The final time in the value is the no-show
     * cutoff. Returning null keeps cancellation unavailable for incomplete
     * or legacy schedules that cannot be interpreted safely.
     */
    public function pickupDeadline(): ?CarbonImmutable
    {
        if (! $this->pickup_date || blank($this->pickup_slot)) {
            return null;
        }

        preg_match_all(
            '/(?:0?[1-9]|1[0-2]):[0-5][0-9]\s*(?:AM|PM)/i',
            $this->pickup_slot,
            $matches,
        );

        $pickupEndTime = end($matches[0]);

        if ($pickupEndTime === false) {
            return null;
        }

        return CarbonImmutable::createFromFormat(
            'Y-m-d g:i A',
            $this->pickup_date->format('Y-m-d').' '.strtoupper($pickupEndTime),
            config('app.timezone'),
        );
    }

    /**
     * Whether a refund or exchange UBAP made can be recorded against this
     * order's lines. See RESOLVABLE_STATUSES.
     */
    public function canRecordItemResolutions(): bool
    {
        return in_array($this->status, self::RESOLVABLE_STATUSES, true) && ! $this->trashed();
    }

    /**
     * Admin no-show cancellation is only valid after a ready order's
     * complete pickup period has elapsed.
     */
    public function canBeCancelledForNoShow(?CarbonInterface $at = null): bool
    {
        $pickupDeadline = $this->pickupDeadline();

        if ($this->status !== 'ready_for_pickup' || ! $pickupDeadline) {
            return false;
        }

        $at ??= now();

        return $at->greaterThanOrEqualTo($pickupDeadline);
    }

    /**
     * Build the stored pickup_slot string from two clock times.
     *
     * pickupDeadline() parses the last time back out of this value, so every
     * path that writes a schedule must go through here to keep the format.
     */
    public static function pickupSlotFrom(string $startTime, string $endTime): string
    {
        return CarbonImmutable::parse($startTime)->format('g:i A')
            .' - '
            .CarbonImmutable::parse($endTime)->format('g:i A');
    }

    /**
     * Whether UBAP can move this order's pickup to another day, e.g. because
     * the customer asked to collect later. Unlike no-show cancellation this
     * does not wait for the pickup period to end: a customer who calls ahead
     * is exactly who it is for, and a missed pickup can still be moved too.
     */
    public function canBeRescheduled(): bool
    {
        return in_array($this->status, self::RESCHEDULABLE_STATUSES, true)
            && $this->pickup_date !== null
            && ! $this->trashed();
    }

    /**
     * Move the pickup to a new date and time interval.
     *
     * The very first schedule is copied into original_pickup_* the first time
     * this runs and never overwritten, so "originally scheduled for" stays
     * true however many times the order is moved. Each move, with the old and
     * new schedule, is written to the status history as well.
     *
     * The row is re-read under a lock so a reschedule cannot interleave with
     * a completion or cancellation of the same order. Returns the fresh order,
     * or null if it is no longer in a state that can be rescheduled. Throws a
     * ValidationException keyed on pickup_date for a date in the past or a
     * schedule identical to the current one.
     */
    public function reschedulePickup(
        CarbonInterface|string $date,
        string $slot,
        ?string $reason = null,
        ?int $userId = null,
    ): ?self {
        $newDate = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();

        if ($newDate->lt(today())) {
            throw ValidationException::withMessages([
                'pickup_date' => 'The new pickup date cannot be in the past.',
            ]);
        }

        return DB::transaction(function () use ($newDate, $slot, $reason, $userId): ?self {
            $order = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order?->canBeRescheduled()) {
                return null;
            }

            if ($order->pickup_date->isSameDay($newDate) && $order->pickup_slot === $slot) {
                throw ValidationException::withMessages([
                    'pickup_date' => 'The new schedule is the same as the current one.',
                ]);
            }

            // OrderStatusHistory::rescheduleDetails() parses this sentence
            // back apart for the admin view; change the two together.
            $from = $order->pickup_date->format('M d, Y').' ('.$order->pickup_slot.')';
            $to = $newDate->format('M d, Y').' ('.$slot.')';
            $note = self::RESCHEDULE_NOTE_PREFIX." from {$from} to {$to}.";

            if (filled($reason)) {
                $note .= ' Reason: '.trim($reason);
            }

            $order->updateStatus($order->status, $note, $userId, [
                'original_pickup_date' => $order->original_pickup_date ?? $order->pickup_date,
                'original_pickup_slot' => $order->original_pickup_slot ?? $order->pickup_slot,
                'pickup_date' => $newDate->toDateString(),
                'pickup_slot' => $slot,
                'rescheduled_at' => now(),
                'reschedule_count' => $order->reschedule_count + 1,
            ]);

            return $order->fresh(['customer', 'items']);
        });
    }

    /**
     * Put this order's units back on the shelf.
     *
     * Checkout takes stock out at the moment the order is written, so
     * nothing returns it unless this does. It lives on the model rather
     * than in the page that cancels, because the status can be changed
     * from several places -- the customer's cancel button, the admin's
     * status dropdown, a future bulk action -- and every one of them has
     * to credit stock the same way or the product module drifts.
     *
     * stock_restored_at is what makes this safe to call more than once.
     * The order row is locked and re-read inside the transaction before
     * the column is checked, so two people cancelling the same order at
     * the same moment cannot both pass the check and credit stock twice.
     *
     * Products are locked before variants, matching the order checkout
     * takes them in, so a cancel and a checkout cannot deadlock against
     * each other. Rows within each group are locked by ascending id for
     * the same reason.
     *
     * Returns whether this call was the one that did the crediting.
     */
    public function restoreStock(): bool
    {
        return DB::transaction(function (): bool {
            $order = static::withTrashed()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || $order->stock_restored_at !== null) {
                return false;
            }

            $productQuantities = [];
            $variantQuantities = [];

            foreach ($order->items()->get() as $item) {
                // A line whose product was force-deleted keeps its snapshot
                // but has no stock left to credit, so there is nothing to do
                // for it. The same goes for a deleted variant.
                if ($item->product_variant_id) {
                    $variantQuantities[$item->product_variant_id] =
                        ($variantQuantities[$item->product_variant_id] ?? 0) + $item->quantity;
                } elseif ($item->product_id) {
                    $productQuantities[$item->product_id] =
                        ($productQuantities[$item->product_id] ?? 0) + $item->quantity;
                }
            }

            // Sum per product first. An order can hold more than one line for
            // the same item, and each row must only be touched once.
            ksort($productQuantities);
            ksort($variantQuantities);

            foreach ($productQuantities as $productId => $quantity) {
                // Soft-deleted products are included. The units physically
                // came back, and the product can still be restored.
                Product::withTrashed()
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('stock_quantity', $quantity);
            }

            foreach ($variantQuantities as $variantId => $quantity) {
                ProductVariant::whereKey($variantId)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('stock_quantity', $quantity);
            }

            // Quietly, so this write does not re-enter the status hook below.
            $order->forceFill(['stock_restored_at' => now()])->saveQuietly();
            $this->stock_restored_at = $order->stock_restored_at;

            return true;
        });
    }

    /**
     * Build a code that no other order is using.
     *
     * Both columns this feeds are uniquely indexed, so a repeat would
     * fail the insert. uniqid() is derived from the clock and can repeat
     * when two orders are created in the same microsecond, which is why
     * the candidate is checked against the table instead.
     *
     * Soft-deleted orders are included in the check. Their rows are still
     * present, so they still occupy the unique index.
     */
    protected static function generateUniqueCode(string $column, string $prefix): string
    {
        do {
            $code = $prefix.strtoupper(Str::random(8));
        } while (static::withTrashed()->where($column, $code)->exists());

        return $code;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateUniqueCode('order_number', 'ORD-');
            }
        });

        // The claim number is what the customer presents at the UBAP office,
        // so it is only issued once the order is actually sitting there to
        // be collected. Orders that never reach that point never get one.
        //
        // This hangs off saving rather than off updateStatus() so that it
        // also fires when the status is changed straight through the admin
        // panel, and the code is written in the same query as the status.
        static::saving(function ($order) {
            if ($order->status === 'ready_for_pickup' && empty($order->claim_number)) {
                $order->claim_number = static::generateUniqueCode('claim_number', 'CLM-');
            }
        });

        static::created(function ($order) {
            $order->statusHistories()->create([
                'status' => $order->status,
                'notes' => 'Order created',
            ]);
        });

        // Credit stock back the moment the order stops being a sale.
        //
        // This hangs off the model rather than off the pages that change the
        // status, so the customer's cancel button and the admin's status
        // dropdown restock identically. Before this, only the customer's
        // button did, and an order cancelled from the admin panel left its
        // units permanently missing from stock_quantity.
        //
        // An order moved back out of a cancelled status is deliberately not
        // re-deducted -- nothing here can know the stock is still there to
        // take. restoreStock() will refuse to credit it a second time, so the
        // worst case is stock that reads high and is corrected by hand,
        // rather than stock credited twice.
        static::updated(function ($order) {
            if ($order->wasChanged('status')
                && in_array($order->status, self::RESTOCKING_STATUSES, true)) {
                $order->restoreStock();
            }
        });
    }
}
