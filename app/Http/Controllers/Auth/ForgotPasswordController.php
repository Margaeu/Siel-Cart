<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Display the form to request a password reset link.
     */
    public function showLinkRequestForm()
    {
        return view('auth.customer.forgot-password');
    }

    /**
     * Send a password reset link to the given customer.
     *
     * The response is identical whatever the broker returns. Echoing the broker
     * status told a caller whether an address was registered: INVALID_USER
     * ("We can't find a user with that email address.") for unknown emails, and
     * RESET_THROTTLED only ever for real accounts, since the broker only tracks
     * tokens for customers that exist.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __('If an account exists for that email address, we have sent a password reset link to it.'));
    }
}