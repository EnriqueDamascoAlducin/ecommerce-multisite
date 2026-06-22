<?php

use App\Http\Controllers\Storefront\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('carrito')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/', [CartController::class, 'store'])->name('store');
    Route::post('envio', [CartController::class, 'shipping'])->name('shipping');
    Route::post('cupon', [CartController::class, 'applyCoupon'])->name('coupon.apply');
    Route::delete('cupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
    Route::patch('{item}', [CartController::class, 'update'])->name('update');
    Route::delete('{item}', [CartController::class, 'destroy'])->name('destroy');
});
