<?php

use App\Filament\Pages\Auth\ResetPassword as AdminResetPasswordPage;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Azure App Service terminates HTTPS at its front end and forwards the
        // request as plain HTTP with X-Forwarded-* headers. Without trusting
        // them, Laravel builds http:// URLs: pages load assets as mixed content,
        // and signed email-verification links fail with "Invalid signature"
        // because the signature was made for a different scheme. '*' is safe
        // here because the app is only reachable through Azure's front end.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'verified.customer' => EnsureCustomerEmailIsVerified::class,
        ]);

        // Redirect authenticated customers to /my-account when hitting guest/login routes
        $middleware->redirectTo(
            guests: '/login',
            users: function (Request $request) {
                if (auth('customer')->check()) {
                    return '/my-account';
                }

                return '/my-account';
            }
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Three routes in this app carry a signed URL: customer email
        // verification (`verification.verify`), the admin panel's invitation /
        // password-reset link (`filament.admin.auth.password-reset.reset` -
        // Filament puts `signed` on it even though AdminInvitation issues a
        // permanent signature; only a tampered link fails it there, since the
        // real expiry is the `users`-broker token row App\Filament\Pages\Auth\
        // ResetPassword::mount() already checks), and Filament's own export
        // download link. Routing by name here keeps the wording honest instead
        // of telling an admin their "verification link" expired.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            return match ($request->route()?->getName()) {
                'verification.verify' => response()->view('errors.link-expired', [
                    'title' => 'Verification Link Expired',
                    'heading' => 'This verification link has expired',
                    'message' => 'For your security, verification links expire quickly. Request a new one to activate your account.',
                    'primaryLabel' => auth('customer')->check() ? 'Resend verification email' : 'Sign in to continue',
                    'primaryUrl' => auth('customer')->check() ? route('verification.notice') : route('login'),
                ], 403),
                'filament.admin.auth.password-reset.reset' => response()->view(
                    'errors.link-expired',
                    AdminResetPasswordPage::expiredLinkViewData($request->boolean('invitation')),
                    403
                ),
                default => response()->view('errors.link-expired', [
                    'title' => 'Link Expired',
                    'heading' => 'This link is no longer valid',
                    'message' => 'For your security, this link has expired or was already used.',
                    'primaryLabel' => 'Go to Homepage',
                    'primaryUrl' => route('home'),
                ], 403),
            };
        });
    })->create();
