<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Category extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'is_active',
        'sort_order',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'slug',
                'image',
                'is_active',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    #[Scope()]
    protected function active(Builder $builder)
    {
        $builder->where('is_active', true);
    }

    #[Scope()]
    protected function sorted(Builder $builder)
    {
        $builder->orderBy('sort_order', 'asc');
    }

    public const EMPTY_SLUG_MESSAGE = 'The name must contain at least one letter or number.';

    public const ASSIGNED_PRODUCTS_MESSAGE = 'Products are still assigned to it, including any that are inactive or deleted. Reassign those products to another category or permanently delete them first.';

    // relationship between the products and category
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The one "can this category be deleted" check shared by the edit-page
     * and bulk delete actions. withTrashed() matters: category_id is required,
     * so a soft-deleted product still references the category, and the
     * database's RESTRICT foreign key would refuse the delete anyway.
     */
    public function hasAssignedProducts(): bool
    {
        return $this->products()->withTrashed()->exists();
    }

    /**
     * Why this category cannot be deactivated right now, or null if it can.
     *
     * An inactive category is dropped from the storefront menus, but product
     * queries only check the product's own is_active, so an active product
     * left inside it would stay on sale under a category nobody can browse
     * to. The rule is therefore that an inactive category holds no active
     * products: the admin moves them to another category (or deactivates
     * them) first. Trashed and inactive products are already off the
     * storefront, so they do not block. Product::boot() enforces the other
     * half, so the rule cannot be broken from the product side either.
     */
    public function deactivationBlockedReason(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        $names = $this->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        if ($names->isEmpty()) {
            return null;
        }

        $listed = $names->take(3)->map(fn (string $name) => "\"{$name}\"")->join(', ', ' and ');
        $more = $names->count() > 3 ? ' and '.($names->count() - 3).' more' : '';

        return "This category still has {$names->count()} active "
            .str('product')->plural($names->count())
            ." ({$listed}{$more}). Move them to another category before deactivating it.";
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('r2')->url($this->image);
    }

    protected static function boot()
    {
        parent::boot();

        // Slug generation and its empty check live in one saving hook. They
        // used to be separate creating/updating hooks, and Eloquent fires
        // saving *before* those, so a guard beside them would have checked the
        // slug before it existed. A name like "!!!" slugifies to "", which
        // would store an unreachable storefront filter; the admin form rejects
        // it first, and this stops seeders and tinker from slipping one in.
        //
        // The slug is set once, at creation, and never follows later renames
        // (same rule as Product::boot()): it is the category's storefront
        // filter URL, and regenerating it on every name change broke links
        // that were already shared. The admin form shows it read-only, so
        // nothing besides this hook ever sets it.
        static::saving(function (Category $category) {
            $slug = $category->slug;

            if (! $category->exists && blank($slug)) {
                $slug = Str::slug((string) $category->name);
            }

            if (blank($slug)) {
                throw ValidationException::withMessages([
                    'name' => self::EMPTY_SLUG_MESSAGE,
                ]);
            }

            $category->slug = $slug;
        });

        // Backstop for deactivationBlockedReason(). The admin form checks the
        // same thing first so the message lands under the Active toggle; this
        // stops a bulk action, seeder, or tinker from deactivating anyway.
        static::updating(function (Category $category) {
            if (! $category->isDirty('is_active') || $category->is_active) {
                return;
            }

            if ($reason = $category->deactivationBlockedReason()) {
                throw ValidationException::withMessages(['is_active' => $reason]);
            }
        });

        // Remove the image only once the delete has actually committed, so a
        // blocked or rolled-back delete never loses it. Uploads keep their
        // original filenames, so two categories can point at the same path;
        // leave the file alone while another category still uses it.
        static::deleted(function (Category $category) {
            $image = $category->image;

            if (blank($image)) {
                return;
            }

            DB::afterCommit(function () use ($image) {
                if (Category::query()->where('image', $image)->exists()) {
                    return;
                }

                // The r2 disk throws on failure. The category row is already
                // gone by now, so an R2 outage must not surface as "delete
                // failed" for a delete that committed -- report the orphaned
                // file and let the request finish.
                try {
                    Storage::disk('r2')->delete($image);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }
}
