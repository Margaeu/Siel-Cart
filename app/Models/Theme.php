<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'primary_color',
        'secondary_color',
        'font_family',
        'custom_font_name',
        'custom_font_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The one theme currently live on the storefront.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Only one theme may be active at a time: whenever a theme is saved as
     * active, every other theme is switched off in the same request.
     */
    protected static function booted(): void
    {
        static::saving(function (Theme $theme) {
            if ($theme->is_active) {
                static::query()
                    ->where('id', '!=', $theme->id ?? 0)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Absolute R2 URL for the custom font file, if one is attached.
     */
    public function getCustomFontUrlAttribute(): ?string
    {
        if (!$this->custom_font_path) {
            return null;
        }

        return Storage::disk('r2')->url($this->custom_font_path);
    }

    /**
     * The name to use in CSS `font-family:` — the custom font's name if one
     * is attached, otherwise the chosen preset (e.g. 'Inter').
     */
    public function getResolvedFontNameAttribute(): string
    {
        return $this->custom_font_name ?: ($this->font_family ?: 'Inter');
    }
}
