<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'is_active',
        'remember_token',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to only active customers.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // Relationships with the other features

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the customer's permanent shopping cart.
     *
     * Each customer has one cart stored in the database.
     * The cart remains available when the customer logs in again.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    // Helper Methods

    /**
     * Get the customer's initials.
     */
    public function initials(): string
    {
        $firstName = mb_substr($this->first_name ?? '', 0, 1);
        $lastName = mb_substr($this->last_name ?? '', 0, 1);

        $initials = strtoupper($firstName . $lastName);

        return $initials ?: 'CU';
    }

    public function getTotalSpentAttribute()
    {
        return $this->orders()->where('payment_status', 'paid')->sum('total');
    }

    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}