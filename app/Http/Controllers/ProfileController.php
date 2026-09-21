<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Check if customer has ongoing orders before allowing account state changes.
     */
    private function hasActiveOrders(Customer $user): bool
    {
        return $user->orders()
            ->whereIn('status', [
                'pending',
                'processing',
                'preparing',
                'out_for_delivery',
                'ready_for_pickup',
                'ready',
            ])
            ->exists();
    }

    /**
     * DEACTIVATE ACCOUNT (Logs out & redirects to Login page)
     */
    public function deactivateAccount(Request $request)
    {
        /** @var Customer|null $user */
        $user = Auth::guard('customer')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($this->hasActiveOrders($user)) {
            return back()->with('deactivate_error', 'Cannot deactivate account while you have active orders or orders ready for pickup.');
        }

        // 1. Update customer status
        $user->is_active = false;
        $user->deactivated_at = now();
        $user->save();

        // 2. Explicitly log out customer guard
        Auth::guard('customer')->logout();

        // 3. Invalidate current session & regenerate token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. Redirect to login page with notice
        return redirect()->route('login')->with('message', 'Your account has been deactivated. You can reactivate it anytime within 30 days by logging back in.');
    }

    /**
     * DELETE ACCOUNT IMMEDIATELY (Logs out & redirects to Login page)
     */
    public function deleteAccount(Request $request)
    {
        /** @var Customer|null $user */
        $user = Auth::guard('customer')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($this->hasActiveOrders($user)) {
            return back()->with('error', 'Cannot delete account while you have active orders or orders ready for pickup.');
        }

        // 1. Anonymize email and set inactive
        $user->email = 'deleted_' . time() . '_' . $user->email;
        $user->is_active = false;
        $user->deactivated_at = now();
        $user->save();

        // 2. Explicitly log out customer guard
        Auth::guard('customer')->logout();

        // 3. Invalidate current session & regenerate token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. Redirect to login page with notice
        return redirect()->route('login')->with('message', 'Your account has been permanently deleted.');
    }
}