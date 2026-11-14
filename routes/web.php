<?php

use App\Http\Controllers\Admin\BallTypeController as AdminBallTypeController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::get('/order', [OrderController::class, 'create'])->name('order.create');
Route::post('/order', [OrderController::class, 'store'])->name('order.store');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
|
| Catalogue management, including uploading and cropping photos of the real
| balls. Gated by the `is_admin` flag on the user.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::redirect('/', '/admin/ball-types');

        Route::get('ball-types', [AdminBallTypeController::class, 'index'])->name('ball-types.index');
        Route::get('ball-types/create', [AdminBallTypeController::class, 'create'])->name('ball-types.create');
        Route::post('ball-types', [AdminBallTypeController::class, 'store'])->name('ball-types.store');
        Route::get('ball-types/{ballType}/edit', [AdminBallTypeController::class, 'edit'])->name('ball-types.edit');
        Route::put('ball-types/{ballType}', [AdminBallTypeController::class, 'update'])->name('ball-types.update');
        Route::delete('ball-types/{ballType}', [AdminBallTypeController::class, 'destroy'])->name('ball-types.destroy');
        Route::delete('ball-types/{ballType}/photo', [AdminBallTypeController::class, 'destroyPhoto'])->name('ball-types.photo.destroy');
    });
});
