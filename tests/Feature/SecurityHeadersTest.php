<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // As production: no Vite dev server, whatever the developer running
        // the suite has going in another terminal (public/hot).
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));
    }

    public function test_no_content_security_policy_is_sent_while_vite_is_running_hot(): void
    {
        $hot = storage_path('framework/testing/vite-hot-'.uniqid());
        @mkdir(dirname($hot), 0775, true);
        file_put_contents($hot, 'http://[::1]:5173');
        Vite::useHotFile($hot);

        try {
            $this->get('/about')
                ->assertHeaderMissing('Content-Security-Policy-Report-Only')
                // The rest still apply in development.
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        } finally {
            @unlink($hot);
        }
    }

    /**
     * The storefront (web group), the admin panel (Filament's own stack), and
     * a route outside every group all have to carry the headers -- which is
     * why the middleware is global.
     */
    public static function pages(): array
    {
        return [
            'storefront' => ['/about'],
            'admin login' => ['/admin/login'],
            'readiness probe' => ['/health/ready'],
        ];
    }

    #[DataProvider('pages')]
    public function test_hardening_headers_are_sent(string $uri): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_the_content_security_policy_is_report_only_for_now(): void
    {
        $response = $this->get('/about');

        $response->assertHeaderMissing('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
    }

    public function test_r2_media_is_allowed_by_origin(): void
    {
        config(['filesystems.disks.r2.url' => 'https://media.example.com/some/prefix']);

        $csp = $this->get('/about')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertMatchesRegularExpression("#img-src [^;]*https://media\.example\.com(;| )#", $csp);
        $this->assertMatchesRegularExpression("#media-src [^;]*https://media\.example\.com(;| )#", $csp);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('http://localhost/about')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/about')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_https_behind_azures_proxy_counts_as_secure(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://localhost/about')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
