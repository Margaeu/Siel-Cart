<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            //'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean'
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name',
                'last_name',
                'email',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Replace this admin's roles and record the change in the activity log.
     *
     * Roles live in Spatie's `model_has_roles` pivot, so assigning them never
     * dirties a `users` column and LogsActivity above never sees it. Filament's
     * relationship Select writes the pivot with a raw `sync()`, and Spatie's
     * own RoleAttached/RoleDetached events don't fire for that either (and
     * `syncRoles()` would report every kept role as freshly attached). So the
     * before/after comparison is done here, and written as one `updated` row
     * shaped like a model diff — the presenter shows it as "Roles: ubap →
     * ubap, super_admin" with no special casing.
     *
     * This does not check panel-lockout rules; callers must already have run
     * canLosePanelAccessBy() (UserForm's roles rule does).
     *
     * @param  iterable<int, int|string>  $roleIds
     */
    public function syncRolesAndLog(iterable $roleIds): void
    {
        $before = $this->roles()->pluck('name')->sort()->values()->all();

        $this->syncRoles(collect($roleIds)->all());

        $after = $this->roles()->pluck('name')->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        activity()
            ->performedOn($this)
            ->event('updated')
            ->withProperties([
                'old' => ['roles' => $before],
                'attributes' => ['roles' => $after],
            ])
            ->log('Changed roles');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->first_name . ' ' . $this->last_name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Local scope
    #[Scope()]
    public function active(Builder $builder){
        $builder->where('is_active',true);
    }

    // relationship
    public function orderStatusHistories(){
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Whether this is the only active super admin left, in which case
     * deactivating it would leave the panel with no full-access account.
     */
    public function isLastActiveSuperAdmin(): bool
    {
        if (! $this->hasRole('super_admin')) {
            return false;
        }

        return ! static::query()
            ->active()
            ->role('super_admin')
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    /**
     * Whether $actor may strip this account of panel access — by deactivating
     * it, deleting it, or taking away its super admin role. Blocks
     * self-lockout and the removal of the last active super admin.
     */
    public function canLosePanelAccessBy(?self $actor): bool
    {
        return ! $actor?->is($this) && ! $this->isLastActiveSuperAdmin();
    }

    /**
     * Why this account may not lose panel access, phrased with $action
     * ('deactivate', 'delete'), or null when it may.
     */
    public function panelAccessLossBlockedReason(?self $actor, string $action): ?string
    {
        if ($actor?->is($this)) {
            return "You cannot {$action} your own account.";
        }

        if ($this->isLastActiveSuperAdmin()) {
            return "This is the last active super admin — you cannot {$action} it until another super admin is active.";
        }

        return null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(['super_admin', 'ubap', 'stratcom']);
    }
}
