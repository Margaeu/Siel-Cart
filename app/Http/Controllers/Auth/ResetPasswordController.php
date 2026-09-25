<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, $token = null)
    {
        $email = $request->email;

        // Both a malformed link (no token/email) and an expired or already-used
        // one land on the same branded "link expired" page rather than two
        // different failure UIs - to the customer they're both "this link
        // doesn't work", and the fix is identical: request a new one.
        $expiredResponse = fn () => response()->view('errors.link-expired', [
            'title' => 'Reset Link Expired',
            'heading' => 'This reset link has expired',
            'message' => 'For your security, password reset links expire quickly. Request a new one to continue.',
            'primaryLabel' => 'Request a new reset link',
            'primaryUrl' => route('password.request'),
            'secondaryLabel' => 'Back to Sign In',
            'secondaryUrl' => route('login'),
        ], 419);

        if (! $token || ! $email) {
            return $expiredResponse();
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

        if (! $user || ! $broker->tokenExists($user, $token)) {
            return $expiredResponse();
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
