<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('path.public', function () {
            return realpath(base_path() . '/../public_html/ecommerce');
        });
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

            $chatbot = new Process(
                ['npm.cmd', 'start'],
                $chatbotPath
            );

            $chatbot->setTimeout(null);
            $chatbot->start();

            echo PHP_EOL; 
            echo " Chatbot server starting on port 3000" . PHP_EOL;
            echo PHP_EOL;
        }

        // The auth activity-log listeners (LogSuccessfulAdminLogin, LogAdminLogout,
        // LogFailedAdminLogin) are NOT registered here on purpose: Laravel's event
        // discovery already picks up everything in app/Listeners. Registering them
        // with Event::listen() as well made every login/logout/failed attempt
        // write two identical activity-log rows.
    }
}