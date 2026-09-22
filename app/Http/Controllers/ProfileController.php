<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * PERMANENTLY DELETE ACCOUNT (Logs out & redirects to Login page)
     *
     * There is no deactivation or recovery window any more: the account is
     * anonymized and soft-deleted by Customer::deleteAccount(), which also
     * refuses while an order is still active.
     */
    public function deleteAccount(Request $request)
    {
        /** @var Customer|null $user */
        $user = Auth::guard('customer')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        try {
            $user->deleteAccount();
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first('account'));
        }

        // 1. Explicitly log out customer guard
        Auth::guard('customer')->logout();

        // 2. Invalidate current session & regenerate token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 3. Redirect to login page with notice. 'status' is the flash key the
        //    customer login view renders.
        return redirect()->route('login')->with('status', 'Your account has been permanently deleted. Your personal information has been removed, and this cannot be undone.');
    }
}
