<?php

use App\Http\Controllers\Api\ChatController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', HomePage::class)->name('home');

Route::get('products', ProductListing::class)->name('products.index');
Route::get('product/{slug}', ProductDetails::class)->name('products.show');
Route::get('/cart', CartPage::class)->name('cart.index');

// API Route for the AI Chatbot
Route::post('/api/chat', [ChatController::class, 'store']);

// protected customer routes
Route::middleware('auth:customer')->group(function () {
    Route::middleware('verified.customer')->group(function () {
        Route::get('/checkout', CheckoutPage::class)->name('checkout');
        Route::get('/my-account', Dashboard::class)->name('customer.dashboard');

        Route::get('/my-account/orders', Orders::class)->name('customer.orders');
        Route::get('/my-account/orders/{id}', OrderDetails::class)->name('customer.orders.show');
        Route::get('/my-account/profile', App\Livewire\Customer\Profile::class)->name('customer.profile');
    });

    // logout
    Route::post('/logout', function () {
        /** @var StatefulGuard $guard */
        $guard = auth('customer');
        $guard->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');
});
