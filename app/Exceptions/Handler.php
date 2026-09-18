<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Laravel\Fortify\Fortify;
use Throwable;

class Handler extends ExceptionHandler
{
	/**
	 * A list of the exception types that are not reported.
	 *
	 * @var array<int, class-string<\Throwable>>
	 */
	protected $dontReport = [
		//
	];

	/**
	 * A list of the inputs that are never flashed for validation exceptions.
	 *
	 * @var array<int, string>
	 */
	protected $dontFlash = [
		'current_password',
		'password',
		'password_confirmation',
	];

	/**
	 * Register the exception handling callbacks for the application.
	 */
	public function register(): void
	{
		$this->renderable(function (ThrottleRequestsException $e, Request $request) {
			if ($request->wantsJson() || $request->expectsJson()) {
				return response()->json(['message' => $e->getMessage() ?: 'Too many requests'], 429);
			}

			// Try to compute remaining lockout time using the same throttle key.
			try {
				$throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
				$seconds = RateLimiter::availableIn($throttleKey);
				if ($seconds > 0) {
					$minutes = (int) ceil($seconds / 60);
					$message = "Too many failed login attempts. Please try again in {$minutes} minutes.";
				} else {
					$message = $e->getMessage() ?: 'Too many requests';
				}
			} catch (\Throwable $ex) {
				$message = $e->getMessage() ?: 'Too many requests';
			}

			// Redirect back to the login page and show the friendly message where
			// Fortify normally shows the password/email validation error.
			return redirect()->route('login')
				->withErrors([Fortify::username() => $message]);
		});

		// Also handle Symfony's TooManyRequestsHttpException which may be thrown
		// by the framework's throttle middleware, otherwise Laravel shows the
		// default 429 error page.
		$this->renderable(function (TooManyRequestsHttpException $e, Request $request) {
			if ($request->wantsJson() || $request->expectsJson()) {
				return response()->json(['message' => $e->getMessage() ?: 'Too many requests'], 429);
			}

			try {
				$throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
				$seconds = RateLimiter::availableIn($throttleKey);
				if ($seconds > 0) {
					$minutes = (int) ceil($seconds / 60);
					$message = "Too many failed login attempts. Please try again in {$minutes} minutes.";
				} else {
					$message = $e->getMessage() ?: 'Too many requests';
				}
			} catch (\Throwable $ex) {
				$message = $e->getMessage() ?: 'Too many requests';
			}

			return redirect()->route('login')
				->withErrors([Fortify::username() => $message]);
		});

	}

	/**
	 * Render an exception into an HTTP response.
	 */
	public function render($request, Throwable $e)
	{
		// If any exception has a 429 status code, redirect to the login page
		// and show the friendly lockout message instead of the default 429 page.
		if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 429) {
			try {
				$throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
				$seconds = RateLimiter::availableIn($throttleKey);
				if ($seconds > 0) {
					$minutes = (int) ceil($seconds / 60);
					$message = "Too many failed login attempts. Please try again in {$minutes} minutes.";
				} else {
					$message = $e->getMessage() ?: 'Too many requests';
				}
			} catch (\Throwable $ex) {
				$message = $e->getMessage() ?: 'Too many requests';
			}

			return redirect()->route('login')
				->withErrors([Fortify::username() => $message]);
		}

		return parent::render($request, $e);
	}
}
