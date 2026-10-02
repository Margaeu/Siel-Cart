<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\LogsAdminActivity;

class Banner extends Model
{
    // LogsActivity was imported and getActivitylogOptions() written below, but the
    // trait itself was never applied to the class -- so the allowlist was dead code
    // and a banner change wrote no audit row (0 of 178 rows in the log were
    // Banner's). Product, Order, Category and User all apply it; this one was
    // missed. Found because Pint flagged the import as unused.
    use HasFactory, LogsAdminActivity;

    /**
     * Extensions the carousel renders with <video> instead of <img>.
     *
     * The column is still called `image_path` because a banner was stills-only
     * until clips were added; the stored value is whatever the upload field
     * accepted. Keep this in step with the accepted MIME types in
     * BannerForm — an extension accepted there but missing here renders as a
     * broken <img>, and one listed here but not accepted there is dead weight.
     */
    public const VIDEO_EXTENSIONS = ['mp4'];

    protected $fillable = [
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'image_path',
                'is_active',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Only banners the Design Admin has switched on.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Storefront display order — lowest sort_order first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('r2')->url($this->image_path);
    }

    /**
     * Whether this banner is a clip rather than a still.
     *
     * Decided on the stored extension rather than a column: the upload field is
     * the only writer of `image_path`, and it already restricts the extension to
     * the handful in BannerForm's accepted MIME types. A NULL or extension-less
     * path answers false, so a half-saved row renders as an <img> that simply
     * shows nothing instead of an empty <video> element.
     */
    public function getIsVideoAttribute(): bool
    {
        $extension = strtolower(pathinfo((string) $this->image_path, PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }
}
