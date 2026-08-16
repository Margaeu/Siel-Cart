<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'subtotal',
        'tax_amount',
        'total',
        'pickup_contact_name',
        'pickup_contact_phone',
        'pickup_date',
        'pickup_location',
        'claim_code',
        'payment_method',
        'payment_status',
        'paymongo_payment_transaction_id',
        'paymongo_checkout_url',
        'paid_at',
        'status',
        'customer_notes',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
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
     * Scope to only shipped orders
     */
    #[Scope]
    protected function readyForPickup(Builder $query): void
    {
        $query->where('status', 'ready_for_pickup');
    }

    /**
     * Scope to only delivered orders
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

    protected static function boot(){
        parent::boot();

        static::creating(function ($order){
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'. strtoupper(uniqid());
            }
        });

        static::created(function($order){
            $order->statusHistories()->create([
                'status' => $order->status,
                'notes' => 'Order created'
            ]);
            //order confirmation email
        });
    }
}
