<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block unverified customers, telling them why they were sent away.
 *
 * Laravel's built in "verified" middleware redirects silently, which drops the
 * customer on the verification notice with no idea what happened. This keeps
 * the same behaviour, including the intended URL, but flashes the reason.
 */
class EnsureCustomerEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer && $customer->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your email address is not verified.');
        }

        return Redirect::guest(URL::route('verification.notice'))
            ->with('verify_reason', $this->reasonFor($request));
    }

    /**
     * Explain the redirect in terms of what the customer was trying to do.
     */
    private function reasonFor(Request $request): string
    {
        return match ($request->route()?->getName()) {
            'checkout' => 'Please verify your email address before checking out. Once verified we will bring you straight back to your order.',
            default => 'Please verify your email address to access your account.',
        };
    }
}
