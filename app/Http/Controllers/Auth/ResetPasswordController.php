<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, $token = null)
    {
        $email = $request->email;

        if (!$token || !$email) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Invalid password reset link.'
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate the reset token BEFORE showing the page
        |--------------------------------------------------------------------------
        */

        /** @var PasswordBroker $broker */
        $broker = Password::broker('customers');

        $user = $broker->getUser([
            'email' => $email,
            'token' => $token,
        ]);

        if (!$user || !$broker->tokenExists($user, $token)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This password reset link has expired or is invalid.'
                ]);
        }

        return view('auth.customer.reset-password')->with([
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::broker('customers')->reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function ($customer, $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($customer));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()
                ->route('login')
                ->with('status', __($status))
            : back()->withErrors([
                'email' => __($status),
            ]);
    }
}