<?php

// Rutas de clientes ecommerce (Fase 9). Se cargan dentro del grupo
// `resolve.store` desde web.php, por lo que el sitio actual ya está resuelto.
// Espacio de URLs bajo /cuenta para no chocar con el auth del admin (Fortify).

use App\Http\Controllers\Storefront\Account\AddressController;
use App\Http\Controllers\Storefront\Account\OrderController;
use App\Http\Controllers\Storefront\Account\ProfileController;
use App\Http\Controllers\Storefront\Auth\LoginController;
use App\Http\Controllers\Storefront\Auth\NewPasswordController;
use App\Http\Controllers\Storefront\Auth\PasswordResetLinkController;
use App\Http\Controllers\Storefront\Auth\RegisterController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\DownloadController;
use App\Http\Controllers\Storefront\PostalCodeLookupController;
use Illuminate\Support\Facades\Route;

// Carrito (invitado o cliente). El carrito de invitado se identifica por la sesión.
require __DIR__.'/storefront-cart.php';

// Checkout (invitado o cliente).
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    Route::get('codigo-postal/{postalCode}', [PostalCodeLookupController::class, 'show'])
        ->name('postal-code.show')
        ->where('postalCode', '[0-9]{5}');
    Route::get('exito/{order}', [CheckoutController::class, 'success'])->name('success');
    Route::get('pendiente/{order}', [CheckoutController::class, 'pending'])->name('pending');
    Route::get('fallo/{order}', [CheckoutController::class, 'failure'])->name('failure');
});

Route::prefix('cuenta')->name('customer.')->group(function () {
    // Invitados (no autenticados como cliente).
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);

        Route::get('registro', [RegisterController::class, 'create'])->name('register');
        Route::post('registro', [RegisterController::class, 'store']);

        Route::get('recuperar', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('recuperar', [PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('restablecer/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('restablecer', [NewPasswordController::class, 'store'])->name('password.store');
    });

    // Clientes autenticados.
    Route::middleware('auth:customer')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', [ProfileController::class, 'edit'])->name('account');
        Route::put('perfil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');

        Route::get('direcciones', [AddressController::class, 'index'])->name('addresses.index');
        Route::post('direcciones', [AddressController::class, 'store'])->name('addresses.store');
        Route::put('direcciones/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('direcciones/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('pedidos', [OrderController::class, 'index'])->name('orders.index');

        // Descargas digitales (productos descargables comprados).
        Route::get('descargas', [DownloadController::class, 'index'])->name('downloads.index');
        Route::get('descargas/{grant}/archivo', [DownloadController::class, 'download'])->name('downloads.file');
    });
});
