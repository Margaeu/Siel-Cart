<?php

namespace App\Observers;

use App\Enums\OrderItemResolutionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRefundResolution;
use App\Services\HomepageRankingCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new homepage-ranking cache generation when an order, order line
 * or refund changes in a way that can move the week's sales map.
 *
 * Two rules keep this correct under transactions:
 *
 *  - Decide at the event, defer only the invalidation. Whether a
 *    ranking-relevant field changed is read here, synchronously, while
 *    wasChanged() still describes this save. An after-commit observer
 *    (ShouldHandleEventsAfterCommit) would only look at the model after
 *    Laravel has synced its original attributes -- and a second save of the
 *    same model in the transaction would have replaced the change set
 *    entirely, so a status change followed by an unrelated edit would be
 *    missed.
 *
 *  - Invalidate after commit. DB::afterCommit() runs the callback straight
 *    away when no transaction is open, holds it until the outermost commit
 *    when transactions are nested, and discards it on rollback (a rolled
 *    back savepoint included), so a write that never lands never rotates
 *    the generation. Readers inside an open transaction bypass the shared
 *    cache altogether (HomepageRankingCache::salesMap()).
 *
 * Deliberately no "one invalidation per transaction" flag: a rolled-back
 * transaction discards its callback without telling anyone, so the flag
 * would never be cleared and every later commit would be skipped.
 *
 * Order force deletes cascade to order_items and return_refund_resolutions
 * in the database, which fires no events for those rows -- the Order's own
 * forceDeleted event covers them.
 */
class HomepageRankingObserver
{
    /** Order columns the ranking's sales query filters on. */
    private const ORDER_FIELDS = ['status', 'payment_status', 'completed_at'];

    /** Order line columns the ranking's sales query reads. */
    private const ORDER_ITEM_FIELDS = ['order_id', 'product_id', 'quantity'];

    public function __construct(private readonly HomepageRankingCache $cache) {}

    public function created(Model $model): void
    {
        $relevant = match (true) {
            // Checkout creates orders as pending, which the ranking never
            // counts; an order only starts to count via a later update.
            $model instanceof Order => $this->orderQualifies($model),
            $model instanceof OrderItem => $this->orderIdQualifies($model->order_id),
            // Refund resolutions only exist for completed orders. An
            // exchange leaves net units unchanged, so it never invalidates.
            $model instanceof ReturnRefundResolution => $model->type === OrderItemResolutionType::Refund,
            default => false,
        };

        if ($relevant) {
            $this->invalidateAfterCommit();
        }
    }

    public function updated(Model $model): void
    {
        $relevant = match (true) {
            $model instanceof Order => $model->wasChanged(self::ORDER_FIELDS),
            // Moving a line between orders affects both the order it left
            // and the order it joined.
            $model instanceof OrderItem => $model->wasChanged(self::ORDER_ITEM_FIELDS)
                && ($this->orderIdQualifies($model->order_id)
                    || $this->orderIdQualifies($model->getOriginal('order_id'))),
            // ReturnRefundResolution is immutable (its model throws on
            // update), so there is nothing to handle for it here.
            default => false,
        };

        if ($relevant) {
            $this->invalidateAfterCommit();
        }
    }

    public function deleted(Model $model): void
    {
        $relevant = match (true) {
            // Soft or force deleted: a deleted order drops out of the sales
            // query either way. Not narrowed to qualifying orders -- the
            // row is gone or trashed, and deletes are rare.
            $model instanceof Order => true,
            $model instanceof OrderItem => $this->orderIdQualifies($model->order_id),
            default => false,
        };

        if ($relevant) {
            $this->invalidateAfterCommit();
        }
    }

    public function restored(Model $model): void
    {
        if ($model instanceof Order) {
            $this->invalidateAfterCommit();
        }
    }

    public function forceDeleted(Model $model): void
    {
        if ($model instanceof Order) {
            $this->invalidateAfterCommit();
        }
    }

    private function invalidateAfterCommit(): void
    {
        DB::afterCommit(fn () => $this->cache->invalidate());
    }

    private function orderQualifies(Order $order): bool
    {
        return $order->status === 'completed' && $order->payment_status === 'paid';
    }

    /**
     * Whether the (not soft-deleted) order counts toward the ranking right
     * now. One indexed lookup at the event, instead of rotating the shared
     * generation -- three cache round trips on the database store -- for
     * every line of every pending checkout.
     */
    private function orderIdQualifies(mixed $orderId): bool
    {
        if (! $orderId) {
            return false;
        }

        return Order::query()
            ->whereKey($orderId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->exists();
    }
}
