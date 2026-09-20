<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogSuccessfulAdminLogin
{
    public function __construct(
        private Request $request,
    ) {}

    public function handle(Login $event): void
    {
        // The guard alone identifies an admin sign-in: only the Filament panel
        // authenticates against `web` (the storefront uses `customer`). Do NOT
        // filter on the request path — the Filament login form is a Livewire
        // component that submits to Livewire's update endpoint, not /admin/*,
        // and a path check silently dropped every real admin login.
        if ($event->guard !== 'web') {
            return;
        }

        // SessionGuard also fires Login when it quietly restores an expired
        // session from the "remember me" cookie. That is not a sign-in, and
        // logging it would add a phantom login whenever an idle session resumes.
        if (Auth::guard($event->guard)->viaRemember()) {
            return;
        }

        activity('authentication')
            ->event('login')
            ->causedBy($event->user)
            ->withProperties([
                'email' => $event->user->email,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ])
            ->log('Successful admin login');
    }
}