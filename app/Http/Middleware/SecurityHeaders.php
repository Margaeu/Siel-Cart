<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening headers for every response, storefront and admin
 * panel alike. Registered globally in bootstrap/app.php rather than on the
 * `web` group, because the Filament panel runs its own middleware stack
 * (AdminPanelProvider::panel()->middleware()) and never enters that group.
 *
 * The Content Security Policy is sent as Report-Only. Livewire, Alpine and
 * Filament rely on inline scripts and Alpine evaluates expressions with
 * `new Function`, so an enforced policy needs 'unsafe-inline'/'unsafe-eval'
 * anyway and a mistake in it blanks the admin panel. Report-Only costs
 * nothing, lists every violation in the browser console, and is switched to
 * enforcing (header name only) once a release has run clean. The other
 * headers are enforced from the start: none of them can break a page this
 * app serves.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Nothing in the app is meant to be framed by another site.
            // frame-ancestors in the CSP is the modern form of this, but the
            // CSP is report-only, so this is what actually stops clickjacking.
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            // Full URLs (order pages, signed verification links) stay on this
            // site; other sites see only the origin.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        // Not while `npm run dev` is serving assets. Vite listens on
        // http://[::1]:5173 here, and browsers reject IPv6 literals in CSP
        // source lists, so it can't be allowed by origin: every dev asset was
        // reported as a violation and the console drowned in them. Production
        // never runs hot, and its assets are same-origin /build/ files.
        if (! Vite::isRunningHot()) {
            $headers['Content-Security-Policy-Report-Only'] = $this->contentSecurityPolicy();
        }

        // Only over HTTPS: browsers ignore it on plain HTTP, and sending it from
        // local http://127.0.0.1 would be meaningless. Azure terminates TLS in
        // front of the app, so isSecure() relies on trustProxies('*') in
        // bootstrap/app.php. No includeSubDomains/preload: those commit every
        // sibling host for a year and can't be taken back quickly.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            // A response that chose its own value keeps it.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // Product, category, banner and review media are served from R2's
        // public URL (config/filesystems.php), images and banner videos alike.
        $media = array_filter([$this->originOf(config('filesystems.disks.r2.url'))]);

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            // fonts.bunny.net: the storefront's web fonts.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'],
            'font-src' => ["'self'", 'data:', 'https://fonts.bunny.net'],
            // data: for the generated SVG avatars (SielAvatarProvider), blob:
            // for upload previews in Filament's file fields.
            'img-src' => ["'self'", 'data:', 'blob:', ...$media],
            'media-src' => ["'self'", 'blob:', ...$media],
            // The chat widget posts to this site's /api/chat; the chatbot
            // service is called server to server and never from the browser.
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    private function originOf(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
