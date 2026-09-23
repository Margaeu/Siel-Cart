<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'verified.customer' => \App\Http\Middleware\EnsureCustomerEmailIsVerified::class,
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
        //
    })->create();