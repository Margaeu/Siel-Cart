<?php

namespace Tests\Feature;

use App\Filament\AvatarProviders\SielAvatarProvider;
use App\Models\Customer;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two head-level costs on every storefront page.
 *
 * The images all come from Cloudflare R2, a different origin, so without a
 * preconnect the browser only starts that DNS lookup and TLS handshake when it
 * reaches the first <img>. And the theme colours were re-queried by every
 * caller that needed them, which in the admin panel meant one query per
 * rendered avatar.
 */
class StorefrontResourceHintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_storefront_preconnects_to_the_r2_origin(): void
    {
        Config::set('filesystems.disks.r2.url', 'https://pub-example.r2.dev');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<link rel="preconnect" href="https://pub-example.r2.dev">', false);
        $response->assertSee('<link rel="dns-prefetch" href="https://pub-example.r2.dev">', false);
    }

    /**
     * A crossorigin preconnect opens a CORS connection that plain <img> loads
     * never reuse, so it would cost a handshake rather than save one.
     */
    public function test_the_preconnect_is_not_crossorigin(): void
    {
        Config::set('filesystems.disks.r2.url', 'https://pub-example.r2.dev');

        $this->get(route('home'))
            ->assertDontSee('href="https://pub-example.r2.dev" crossorigin', false);
    }

    /**
     * A path or query on the configured URL must not leak into the hint --
     * preconnect takes an origin.
     */
    public function test_only_the_origin_is_hinted(): void
    {
        Config::set('filesystems.disks.r2.url', 'https://cdn.example.test/bucket?v=1');

        $this->get(route('home'))
            ->assertSee('<link rel="preconnect" href="https://cdn.example.test">', false)
            ->assertDontSee('cdn.example.test/bucket', false);
    }

    public function test_nothing_is_emitted_when_r2_is_not_configured(): void
    {
        Config::set('filesystems.disks.r2.url', '');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('rel="preconnect"', false);
    }

    public function test_the_active_theme_is_resolved_once_per_request(): void
    {
        Theme::create([
            'name' => 'Default Green',
            'primary_color' => '#557F13',
            'secondary_color' => '#FFD801',
            'is_active' => true,
        ]);

        Theme::forgetActiveMemo();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Ten resolutions stand in for a page rendering ten avatars, each of
        // which used to issue its own query.
        for ($i = 0; $i < 10; $i++) {
            Theme::activeCached();
        }

        $themeQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'themes'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $themeQueries, 'The active theme must be read once, not once per caller.');
    }

    /**
     * The regression this was actually written for. Filament calls the avatar
     * provider once per avatar it renders, and each call used to run its own
     * `select primary_color from themes where is_active = 1` -- measured at 20
     * queries for 20 avatars, every one a round trip to the database.
     */
    public function test_rendering_many_avatars_reads_the_theme_once(): void
    {
        Theme::create([
            'name' => 'Default Green',
            'primary_color' => '#557F13',
            'secondary_color' => '#FFD801',
            'is_active' => true,
        ]);

        Theme::forgetActiveMemo();

        $provider = new SielAvatarProvider;
        $customer = Customer::factory()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        for ($i = 0; $i < 20; $i++) {
            $provider->get($customer);
        }

        $themeQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'themes'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $themeQueries, '20 avatars must not mean 20 theme queries.');
    }

    /**
     * The avatar still follows the theme -- a memo that returned a stale or
     * empty result would silently fall back to CLSU green, which is exactly
     * the kind of regression a query-count test alone would not catch.
     */
    public function test_the_avatar_colour_follows_the_active_theme(): void
    {
        Theme::create(['name' => 'Blue', 'primary_color' => '#2F30AD', 'secondary_color' => '#101010', 'is_active' => true]);
        Theme::forgetActiveMemo();

        $provider = new SielAvatarProvider;
        $method = new \ReflectionMethod($provider, 'themeColor');
        $method->setAccessible(true);

        $this->assertSame([47, 48, 173], $method->invoke($provider));
    }

    public function test_saving_a_theme_clears_the_memo(): void
    {
        Theme::create(['name' => 'Green', 'primary_color' => '#557F13', 'secondary_color' => '#FFD801', 'is_active' => true]);

        $this->assertSame('#557F13', Theme::activeCached()?->primary_color);

        Theme::create(['name' => 'Red', 'primary_color' => '#C41E3A', 'secondary_color' => '#101010', 'is_active' => true]);

        $this->assertSame('#C41E3A', Theme::activeCached()?->primary_color, 'Activating a theme must not leave the old colour cached.');
    }
}
