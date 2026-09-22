<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewCustomer;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Customer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind custom LoginResponse to override default redirect after login
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    // 1. Forget any stored "intended" URL so Laravel doesn't fall back to /dashboard
                    $request->session()->forget('url.intended');

                    // 2. Force immediate session write
                    $request->session()->save();

                    // 3. Redirect directly to /my-account
                    return redirect('/my-account');
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        // configure authentication to use customer guard
        Fortify::authenticateUsing(function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            $maxAttempts = 5;
            $decaySeconds = 5 * 60; // 5 minutes

            if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                $minutes = (int) ceil($seconds / 60);
                throw ValidationException::withMessages([
                    Fortify::username() => "Too many failed login attempts. Please try again in {$minutes} minutes.",
                ]);
            }

            $customer = Customer::where('email', $request->email)->first();

            if ($customer) {
                $passwordMatches = Hash::check($request->password, $customer->password);
            } else {
                Hash::make($request->password);
                $passwordMatches = false;
            }

            if (! $passwordMatches) {
                RateLimiter::hit($throttleKey, $decaySeconds);

                if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
                    $seconds = RateLimiter::availableIn($throttleKey);
                    $minutes = (int) ceil($seconds / 60);
                    throw ValidationException::withMessages([
                        Fortify::username() => "Too many failed login attempts. Please try again in {$minutes} minutes.",
                    ]);
                }

                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.failed'),
                ]);
            }

            RateLimiter::clear($throttleKey);

            // Permanently deleted accounts never reach this point: they are
            // soft-deleted, so the Customer::where() lookup above does not find
            // them and they fall into the shared "unknown email" branch. Logging
            // in never restores or reactivates an account.

            // 1. Deactivated account. Only reached after the password is proven,
            //    so it cannot be used to probe which emails are registered.
            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('This account has been deactivated. Please contact the UBAP Office.'),
                ]);
            }

            // 2. Explicitly log the customer into the customer guard & regenerate session
            Auth::guard('customer')->login($customer, $request->boolean('remember'));
            $request->session()->regenerate();

            return $customer;
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewCustomer::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if (auth('customer')->check()) {
                // Wipe intended destination if hit while authenticated
                $request->session()->forget('url.intended');
                return redirect('/my-account');
            }

            return response()->view('auth.customer.login')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        });
        Fortify::verifyEmailView(fn () => view('auth.customer.verify-email'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('auth.customer.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}