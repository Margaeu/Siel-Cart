<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    /**
     * The active theme, resolved once per request.
     *
     * Not a persistent cache, deliberately. Production runs
     * CACHE_STORE=database against the same remote MySQL, so putting this row
     * in the cache would swap one query to Aiven for another and save nothing
     * -- the cost here is the cross-border round trip, not the lookup. What
     * does cost real queries is calling it repeatedly inside one request, and
     * that is what this removes.
     *
     * The worst offender was SielAvatarProvider: Filament calls an avatar
     * provider once per avatar it renders, and each call issued its own
     * `select primary_color from themes where is_active = 1`. Measured at 20
     * queries for 20 avatars, every one of them a round trip to Singapore.
     *
     * If the cache store ever moves off the database (Redis, or a file store
     * on the instance), wrapping this in Cache::remember becomes worthwhile;
     * until then it would be complexity and staleness risk for nothing.
     *
     * A static is request-scoped under PHP-FPM, where each request is a fresh
     * process state. Tests share one process, so Tests\TestCase::setUp()
     * clears it between tests, and the model events below clear it whenever
     * the active theme actually changes.
     *
     * The branded notifications deliberately keep their own direct lookup:
     * each sends once, so there is nothing to collapse, and AdminInvitation is
     * queued -- a long-running worker would never see the save event that
     * clears this, and could keep mailing an old colour until it restarted.
     */
    private static ?self $activeMemo = null;

    private static bool $activeMemoResolved = false;

    protected $fillable = [
        'name',
        'primary_color',
        'secondary_color',
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
    /**
     * The active theme, reusing this request's already-resolved answer.
     *
     * The flag is only set after the query returns, so a failure (the themes
     * table not existing yet, which AdminPanelProvider and SielAvatarProvider
     * both guard for) is not memoized as "no theme" -- the next call retries
     * and their own try/catch still sees the exception.
     */
    public static function activeCached(): ?self
    {
        if (self::$activeMemoResolved) {
            return self::$activeMemo;
        }

        // query() first: active() is a #[Scope] on a protected method, so
        // calling it as static::active() inside the model resolves to that
        // method instead of going through __callStatic, and throws
        // "cannot be called statically". Going via the builder lets the scope
        // resolve the way every external Theme::active() call already does.
        $theme = static::query()->active()->first();

        self::$activeMemo = $theme;
        self::$activeMemoResolved = true;

        return $theme;
    }

    public static function forgetActiveMemo(): void
    {
        self::$activeMemo = null;
        self::$activeMemoResolved = false;
    }

    protected static function booted(): void
    {
        // Any write can change which row is active -- including the mass
        // update below, which deactivates the others without firing their own
        // events. Clearing on the saved theme covers that, since it is the
        // save that triggered it.
        static::saved(static fn () => static::forgetActiveMemo());
        static::deleted(static fn () => static::forgetActiveMemo());

        static::saving(function (Theme $theme) {
            if ($theme->is_active) {
                static::query()
                    ->where('id', '!=', $theme->id ?? 0)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }
}
