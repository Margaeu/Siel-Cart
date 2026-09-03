<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
     use SoftDeletes;

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
        'paid_at',
        'status',
        'claimant_name',
        'claimant_phone',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'pickup_date' => 'date',
            'paid_at' => 'datetime',
        ];
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

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    // helper methods
    public function updateStatus($newStatus , $notes = null, $userId = null)
    {
        $this->update(['status' => $newStatus]);

        $this->statusHistories()->create([
            'status' => $newStatus,
            'notes' => $notes,
            'user_id' => $userId,
        ]);
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
            $code = $prefix . strtoupper(Str::random(8));
        } while (static::withTrashed()->where($column, $code)->exists());

        return $code;
    }

    protected static function boot(){
        parent::boot();

        static::creating(function ($order){
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
        static::saving(function ($order){
            if ($order->status === 'ready_for_pickup' && empty($order->claim_number)) {
                $order->claim_number = static::generateUniqueCode('claim_number', 'CLM-');
            }
        });

        static::created(function($order){
            $order->statusHistories()->create([
                'status' => $order->status,
                'notes' => 'Order created'
            ]);
        });
    }
}
