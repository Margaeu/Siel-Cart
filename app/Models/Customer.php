<?php

namespace App\Models;

use App\Notifications\CustomerConfirmEmailChange;
use App\Notifications\CustomerResetPassword;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * What every interface shows in place of a deleted customer's identity
     * (name, author, reporter, order customer). It is a display label only:
     * the stored name is an internal placeholder, never this string.
     */
    public const DELETED_LABEL = 'Deleted customer';

    /**
     * What admin views show in place of an erased personal detail (email,
     * phone, birthdate) of a deleted customer, so neither the NULL nor the
     * internal .invalid placeholder email is ever presented as real data.
     */
    public const REMOVED_LABEL = 'Removed';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'pending_email',
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
     * account is deleted, and only the customer can trigger it (the storefront
     * profile page); the admin panel offers no delete or restore.
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
     * Safe to call twice (a double-submitted form, an admin and the customer
     * at once): the row is re-read under lock with trashed rows included, and
     * an already-deleted account is left exactly as it is.
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

            // withTrashed() so a repeat request finds the deleted row and
            // stops, instead of 404ing or anonymizing it a second time.
            $customer = static::withTrashed()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($customer->trashed()) {
                $this->setRawAttributes($customer->getAttributes(), true);

                return;
            }

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
            // The email keeps the id only so it is traceable to this row and
            // unique; the random part stops it being guessable, and .invalid
            // (RFC 2606) guarantees nothing is ever delivered to it.
            // The password is the hash of a random value that is discarded
            // here, so the old hash is gone and no credential can ever match
            // this row again.
            $customer->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => 'deleted-'.$customer->id.'-'.Str::lower(Str::random(32)).'@deleted.invalid',
                'phone' => null,
                'date_of_birth' => null,
                'password' => Hash::make(Str::random(64)),
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

    /**
     * The current email with its local part partly starred out, for display
     * on the profile page (e.g. "ma************n@gmail.com"). The full
     * address is never shown at rest — only revealed by typing it fresh
     * into the "Change" form.
     */
    public function getMaskedEmailAttribute(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email ?? '', 2), 2, '');

        $length = mb_strlen($local);

        $masked = match (true) {
            $length <= 1 => str_repeat('*', max($length, 1)),
            $length <= 3 => mb_substr($local, 0, 1).str_repeat('*', $length - 1),
            default => mb_substr($local, 0, 2).str_repeat('*', $length - 3).mb_substr($local, -1),
        };

        return $domain === '' ? $masked : "{$masked}@{$domain}";
    }

    /**
     * The phone number with everything but the last two digits starred out,
     * for the same "masked until you click Change" display as masked_email.
     */
    public function getMaskedPhoneAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $length = mb_strlen($this->phone);

        return $length <= 2
            ? str_repeat('*', $length)
            : str_repeat('*', $length - 2).mb_substr($this->phone, -2);
    }

    /**
     * Date of birth with the month and day starred out, keeping only the
     * year visible (e.g. asterisks/asterisks/2006). date_of_birth isn't
     * cast (see casts() below), so it is already the raw 'Y-m-d' string the
     * column stores.
     */
    public function getMaskedDateOfBirthAttribute(): ?string
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return '**/**/'.mb_substr($this->date_of_birth, 0, 4);
    }

    /**
     * Send the branded verification email instead of Laravel's default
     * VerifyEmail. Fortify's registration flow and its resend endpoint
     * (verification.send) both call this same method, so overriding it
     * here is the one place that covers both.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomerVerifyEmail);
    }

    /**
     * Send the branded password reset email instead of Laravel's default
     * ResetPassword. Password::broker('customers')->sendResetLink() is the
     * one path that calls this (ForgotPasswordController).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }

    /**
     * Mail the confirmation link for a pending email change to the NEW
     * address, never the current one — Notification::route() bypasses the
     * Notifiable's own routeNotificationForMail(), which would otherwise
     * send to $this->email. Used both when the change is first requested
     * and when the customer asks to resend it.
     */
    public function sendPendingEmailChangeNotification(): void
    {
        if (! $this->pending_email) {
            return;
        }

        Notification::route('mail', $this->pending_email)->notify(new CustomerConfirmEmailChange($this));
    }
}
