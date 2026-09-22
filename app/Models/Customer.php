<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * What every interface shows in place of an erased account detail. It is a
     * display label only: the stored values are NULL or an internal
     * placeholder, never this string (it is not a valid date or email).
     */
    public const DELETED_LABEL = '[Deleted User]';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'date_of_birth', // Customer's date of birth entered during account registration.
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

    /** Reports this customer filed against other customers' reviews. */
    public function reportsFiled()
    {
        return $this->hasMany(Report::class, 'reporter_customer_id');
    }

    /** Reports other customers filed against this customer's reviews. */
    public function reportsReceived()
    {
        return $this->hasMany(Report::class, 'reported_customer_id');
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

    public function hasActiveOrders(): bool
    {
        return $this->orders()->whereIn('status', Order::ACTIVE_STATUSES)->exists();
    }

    /**
     * Permanently delete this account: erase identity and credentials, drop
     * the cart, and soft-delete the row. This is the ONLY way a customer
     * account is deleted, from the storefront or the admin panel.
     *
     * The row itself is kept, soft-deleted, because orders, reviews and reports
     * reference it and their foreign keys cascade on delete — a force delete
     * would wipe the store's order history. deleted_at is the authoritative
     * "this account is gone" flag: Eloquent's SoftDeletingScope keeps the row
     * out of login, remember-me, password reset and session lookups, and there
     * is deliberately no restore path anywhere.
     *
     * No original value is logged or kept anywhere, including inside the
     * replacement email, so the address is immediately free to register again.
     *
     * @throws ValidationException (key "account") while an order is active
     */
    public function deleteAccount(): void
    {
        DB::transaction(function (): void {
            // Lock the cart before checking orders. Checkout locks the same
            // row before it creates an order, so either its order is committed
            // and seen here, or it waits and then finds the cart gone.
            $cart = Cart::where('customer_id', $this->id)->lockForUpdate()->first();

            $customer = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($customer->hasActiveOrders()) {
                throw ValidationException::withMessages([
                    'account' => 'You cannot delete your account while you have an order that is pending, being processed, or ready for pickup.',
                ]);
            }

            // Keyed by email, so it has to go before the email is replaced.
            DB::table('password_reset_tokens')->where('email', $customer->email)->delete();

            if ($cart) {
                $cart->items()->delete();
                $cart->delete();
            }

            // first_name/last_name are NOT NULL, so they get a neutral
            // placeholder; the name accessor shows DELETED_LABEL regardless.
            // The password is random and never revealed (the "hashed" cast
            // hashes it), so no credential can ever match this row again.
            $customer->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => 'deleted-'.Str::uuid().'@anonymized.invalid',
                'phone' => null,
                'date_of_birth' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'is_active' => false,
            ])->save();

            $customer->delete();

            // Keep the caller's instance (often the authenticated user) in
            // step, so nothing downstream reads the erased values off it.
            $this->setRawAttributes($customer->getAttributes(), true);
        });
    }

    /**
     * Get the customer's initials.
     */
    public function initials(): string
    {
        if ($this->trashed()) {
            return '?';
        }

        $firstName = mb_substr($this->first_name ?? '', 0, 1);
        $lastName = mb_substr($this->last_name ?? '', 0, 1);

        $initials = strtoupper($firstName.$lastName);

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
        // Every name display (admin tables, infolists, public reviews) reads
        // this accessor, so a deleted account is labelled in one place.
        if ($this->trashed()) {
            return self::DELETED_LABEL;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }
}
