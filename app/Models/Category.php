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
    protected function active(Builder $builder){
        $builder->where('is_active', true);
    }

    #[Scope()]
    protected function sorted(Builder $builder){
        $builder->orderBy('sort_order','asc');
    }

    public const EMPTY_SLUG_MESSAGE = 'The name must contain at least one letter or number.';

    public const ASSIGNED_PRODUCTS_MESSAGE = 'Products are still assigned to it, including any that are inactive or deleted. Reassign those products to another category or permanently delete them first.';

    // relationship between the products and category
    public function products(){
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

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return Storage::disk('r2')->url($this->image);
    }

    protected static function boot(){
        parent::boot();

        // Slug generation and its empty check live in one saving hook. They
        // used to be separate creating/updating hooks, and Eloquent fires
        // saving *before* those, so a guard beside them would have checked the
        // slug before it existed. A name like "!!!" slugifies to "", which
        // would store an unreachable storefront filter; the admin form rejects
        // it first, and this stops seeders and tinker from slipping one in.
        static::saving(function (Category $category) {
            $slug = $category->slug;

            if (! $category->exists && blank($slug)) {
                $slug = Str::slug((string) $category->name);
            } elseif ($category->exists && $category->isDirty('name') && ! $category->isDirty('slug')) {
                $slug = Str::slug((string) $category->name);
            }

            if (blank($slug)) {
                throw ValidationException::withMessages([
                    'name' => self::EMPTY_SLUG_MESSAGE,
                ]);
            }

            $category->slug = $slug;
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

                Storage::disk('r2')->delete($image);
            });
        });
    }
}