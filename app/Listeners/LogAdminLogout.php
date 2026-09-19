<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class LogAdminLogout
{
    public function __construct(
        private Request $request,
    ) {}

    public function handle(Logout $event): void
    {
        if ($event->guard !== 'web' || ! $event->user) {
            return;
        }

        activity('authentication')
            ->event('logout')
            ->causedBy($event->user)
            ->withProperties([
                'email' => $event->user->email,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ])
            ->log('Admin logged out');
    }
}