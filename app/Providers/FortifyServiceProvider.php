<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewCustomer;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Customer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
            $customer = Customer::where('email', $request->email)->first();

            if (! $customer || ! Hash::check($request->password, $customer->password)) {
                return null;
            }

            // Only tell a caller the account is deactivated once they have
            // proven the password, so this cannot be used to enumerate accounts.
            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('This account has been deactivated. Please contact the shop administrator.'),
                ]);
            }

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
        Fortify::loginView(fn () => view('auth.customer.login'));
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
