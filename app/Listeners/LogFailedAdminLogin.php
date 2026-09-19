<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

class LogFailedAdminLogin
{
    public function __construct(
        private Request $request,
    ) {}

    public function handle(Failed $event): void
    {
        // Filter on the guard only, never the request path: the Filament login
        // form submits through Livewire's update endpoint, not /admin/*, so a
        // path check silently dropped every failed admin attempt.
        if ($event->guard !== 'web') {
            return;
        }

        activity('authentication')
            ->event('login_failed')
            ->withProperties([
                'attempted_email' => $event->credentials['email'] ?? null,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ])
            ->log('Failed admin login attempt');
    }
}