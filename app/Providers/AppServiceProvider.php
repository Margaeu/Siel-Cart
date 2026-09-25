<?php

namespace App\Providers;

use App\Notifications\AdminResetPassword;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // path.public is deliberately left at Laravel's default (public/). It used
        // to be rebound to ../public_html/ecommerce for the old shared-hosting
        // layout; on any host without that folder realpath() returned false, so
        // the Vite manifest, asset() fonts, and public_path() lookups all broke.

        // The admin panel's "forgot password" page builds its mail with
        // app(Filament's ResetPassword::class), so binding it here is the only way
        // to give admins the branded email instead of Laravel's stock layout.
        $this->app->bind(FilamentResetPassword::class, AdminResetPassword::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatically start the chatbot when running:
        // php artisan serve

        if (
            $this->app->runningInConsole() &&
            isset($_SERVER['argv'][1]) &&
            $_SERVER['argv'][1] === 'serve'
        ) {
            $chatbotPath = base_path('chatbot-server');

            if (file_exists($chatbotPath)) {
                $npmBinary = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';

                $chatbot = new Process(
                    [$npmBinary, 'start'],
                    $chatbotPath
                );

                $chatbot->setTimeout(null);
                $chatbot->start();

                echo PHP_EOL;
                echo ' Chatbot server starting on port 3000'.PHP_EOL;
                echo PHP_EOL;
            }
        }

        // The auth activity-log listeners (LogSuccessfulAdminLogin, LogAdminLogout,
        // LogFailedAdminLogin) are NOT registered here on purpose: Laravel's event
        // discovery already picks up everything in app/Listeners. Registering them
        // with Event::listen() as well made every login/logout/failed attempt
        // write two identical activity-log rows..
    }
}
