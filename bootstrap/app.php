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