<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Livewire\Customer\Dashboard;
use App\Livewire\Customer\OrderDetails;
use App\Livewire\Customer\Orders;
use App\Livewire\HomePage;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;


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

Route::view('/return-refund-policy', 'pages.return-refund-policy')
    ->name('return-refund-policy');

Route::view('/faqs', 'pages.faqs')
    ->name('faqs');

Route::match(['GET', 'POST'], '/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware('auth:customer')
    ->name('verification.send');


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
    Route::post('/forgot-password', [
        ForgotPasswordController::class,
        'sendResetLinkEmail'
    ])->name('password.email');

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

Route::post('/api/chat', [
    ChatController::class,
    'store'
]);


/*
|--------------------------------------------------------------------------
| Protected Customer Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:customer')->group(function () {

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

        request()->session()->regenerate();
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