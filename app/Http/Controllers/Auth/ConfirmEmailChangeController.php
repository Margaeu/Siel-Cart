<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

class ConfirmEmailChangeController extends Controller
{
    /**
     * Land a pending email change. The route is signed and hashes the
     * PENDING email (route middleware: signed, throttle:6,1), so this only
     * ever confirms the exact address CustomerConfirmEmailChange mailed —
     * matching the shape of Laravel's own email verification link. Reachable
     * without auth:customer, the same way password reset is: proving you
     * received the mail at the new address is the only authorization this
     * step needs, since requesting the change already required the current
     * password (Profile::requestEmailChange).
     */
    public function confirm(string $customer, string $hash): RedirectResponse
    {
        $customer = Customer::findOrFail($customer);

        if (! $customer->pending_email || ! hash_equals(sha1($customer->pending_email), $hash)) {
            return $this->redirectFor($customer, 'error', 'This email confirmation link is invalid or has already been used.');
        }

        // Re-check uniqueness: another account could have taken the address
        // in the time between the link being sent and being clicked.
        if (Customer::where('email', $customer->pending_email)->where('id', '!=', $customer->id)->exists()) {
            $customer->forceFill(['pending_email' => null])->save();

            return $this->redirectFor($customer, 'error', 'That email address was taken by another account before you confirmed it. Please request the change again with a different address.');
        }

        $customer->forceFill([
            'email' => $customer->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        return $this->redirectFor($customer, 'success', 'Your email address has been updated to '.$customer->email.'.');
    }

    /**
     * Confirming from the browser already logged in as this customer lands
     * back on the profile page, so both success and failure are visible
     * there (RedirectIfAuthenticated would otherwise bounce a login-route
     * redirect straight to the dashboard, silently dropping the flash).
     * Opening the link on another device, or logged out, lands on login —
     * regenerating the session here would log out whoever else is signed
     * into this browser.
     */
    private function redirectFor(Customer $customer, string $outcome, string $message): RedirectResponse
    {
        if (auth('customer')->check() && auth('customer')->id() === $customer->id) {
            return redirect()->route('customer.profile')
                ->with($outcome === 'success' ? 'profile_success' : 'email_change_error', $message);
        }

        if ($outcome === 'success') {
            $message .= ' Please sign in with your new email.';
        }

        return redirect()->route('login')
            ->with($outcome === 'success' ? 'status' : 'error', $message);
    }
}
