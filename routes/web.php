<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\Customer\Dashboard;
use App\Livewire\Customer\OrderDetails;
use App\Livewire\Customer\Orders;
use App\Livewire\HomePage;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', HomePage::class)->name('home');

Route::get('/products', ProductListing::class)
    ->name('products.index');

Route::get('/product/{slug}', ProductDetails::class)
    ->name('products.show');

Route::get('/cart', CartPage::class)
    ->name('cart.index');

Route::view('/about', 'pages.about')
    ->name('about');

Route::view('/privacy-policy', 'pages.privacy-policy')
    ->name('privacy-policy');

Route::view('/terms-and-conditions', 'pages.terms-and-conditions')
    ->name('terms-and-conditions');

// There is no /return-refund-policy or /faqs page. The return/refund policy was
// folded into terms-and-conditions, and a FAQs view was never written; both
// routes used to point at missing views and returned a 500 to anyone who
// typed the URL.

// verification.send is deliberately NOT redefined here. Fortify registers it as
// POST-only behind auth:customer and throttle:6,1. A GET|POST override used to
// shadow that route, which let any link, <img> tag, or prefetch mail a new
// verification email with no CSRF token and no rate limit.


/*
|--------------------------------------------------------------------------
| Customer Password Reset Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest:customer')->group(function () {

    // Forgot Password Form
    Route::get('/forgot-password', [
        ForgotPasswordController::class,
        'showLinkRequestForm'
    ])->name('password.request');

    // Send Password Reset Link
    // Throttled per IP: every request can send an email, so an unthrottled
    // endpoint is both a mail-bombing vector and an enumeration oracle.
    Route::post('/forgot-password', [
        ForgotPasswordController::class,
        'sendResetLinkEmail'
    ])->middleware('throttle:6,1')->name('password.email');

    // Reset Password Form
    Route::get('/reset-password/{token}', [
        ResetPasswordController::class,
        'showResetForm'
    ])->name('password.reset');

    // Update Password
    Route::post('/reset-password', [
        ResetPasswordController::class,
        'update'
    ])->name('password.update');
});


/*
|--------------------------------------------------------------------------
| AI Chatbot API
|--------------------------------------------------------------------------
*/

// Open to guests on purpose, so the throttle is the only thing standing between
// an anonymous caller and unlimited writes to chat_messages plus paid OpenRouter
// calls. Stays in the web group so CSRF still applies.
Route::post('/api/chat', [
    ChatController::class,
    'store'
])->middleware('throttle:10,1');


/*
|--------------------------------------------------------------------------
| Protected Customer Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:customer')->group(function () {

    // Permanent deletion only. The old /account/deactivate route (30-day
    // self-reactivation) is gone; see Customer::deleteAccount().
    Route::post('/account/delete', [ProfileController::class, 'deleteAccount'])
        ->name('account.delete');

    /*
    |--------------------------------------------------------------------------
    | Verified Customer Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('verified.customer')->group(function () {

        // Customer Dashboard
        Route::get('/my-account', Dashboard::class)
            ->name('customer.dashboard');

        // Checkout
        Route::get('/checkout', CheckoutPage::class)
            ->name('checkout');

        // Customer Orders
        Route::get('/my-account/orders', Orders::class)
            ->name('customer.orders');

        // Order Details
        Route::get('/my-account/orders/{id}', OrderDetails::class)
            ->name('customer.orders.show');

        // Customer Profile
        Route::get('/my-account/profile', App\Livewire\Customer\Profile::class)
            ->name('customer.profile');
        });
   
    /*
    |--------------------------------------------------------------------------
    | Customer Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', function () {

        /** @var StatefulGuard $guard */
        $guard = auth('customer');

        $guard->logout();

        // invalidate(), not regenerate(): regenerate() only changes the session
        // id and carries every stored value (intended URL, flashed data, anything
        // a component stashed) over to the new session. invalidate() flushes it,
        // and the fresh CSRF token stops a form captured before logout from being
        // replayed.
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');

    })->name('logout');


// Development-only session debug route. Shows whether the session cookie
// exists, whether the `customer` guard is authenticated, and the sessions
// table row for the cookie value. Only available in local or staging.
Route::get('/dev/session-debug', function (Request $request) {
    if (! app()->environment('local', 'staging')) {
        abort(404);
    }

    $cookieName = config('session.cookie');
    $cookieValue = $request->cookie($cookieName);

    $auth = auth('customer')->check();
    $userId = auth('customer')->id();

    $sessionRow = null;
    if ($cookieValue) {
        $sessionRow = DB::table(config('session.table'))->where('id', $cookieValue)->first();
    }

    return response()->json([
        'environment' => app()->environment(),
        'session_cookie_name' => $cookieName,
        'session_cookie_value' => $cookieValue,
        'auth_customer_check' => $auth,
        'auth_customer_id' => $userId,
        'session_row' => $sessionRow,
    ]);

})->name('dev.session-debug');
});