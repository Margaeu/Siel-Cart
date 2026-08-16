<?php

use App\Livewire\Customer\Orders;
use App\Livewire\CartPage;
use App\Livewire\HomePage;
use Laravel\Fortify\Features;
use App\Livewire\CheckoutPage;
use App\Livewire\ProductDetails;
use App\Livewire\ProductListing;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Customer\Dashboard;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Settings\Appearance;
use Illuminate\Support\Facades\Route;
use App\Livewire\Customer\OrderDetails;
use App\Http\Controllers\CheckoutController;

Route::get('/', HomePage::class)->name('home');


Route::get('products',ProductListing::class)->name('products.index');
Route::get('product/{slug}',ProductDetails::class)->name('products.show');
Route::get('/cart',CartPage::class)->name('cart.index');

//protected customer routes
Route::middleware('auth:customer')->group(function(){
    Route::get('/checkout',CheckoutPage::class)->name('checkout');
    Route::get('/my-account',Dashboard::class)->name('customer.dashboard');

    Route::get('/my-account/orders',Orders::class)->name('customer.orders');
    Route::get('/my-account/orders/{id}',OrderDetails::class)->name('customer.orders.show');
    Route::get('/my-account/profile',\App\Livewire\Customer\Profile::class)->name('customer.profile');

    //checkout success/cancel routes
    Route::get('/checkout/success/{order}', [CheckoutController::class,'success'])->name('checkout.success');
    Route::get('/checkout/cancel/{order}', [CheckoutController::class,'cancel'])->name('checkout.cancel');
    //logout
    Route::post('/logout', function(){
        auth('customer')->logout();
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

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
