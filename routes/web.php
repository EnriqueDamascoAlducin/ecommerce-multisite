<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Storefront público: resuelve el sitio actual (dominio/prefijo) antes de responder.
Route::middleware('resolve.store')->group(function () {
    // Raíz (tiendas por dominio / tienda de entrada).
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('c/{slug}', [StorefrontController::class, 'category'])->name('storefront.category');
    Route::get('p/{slug}', [StorefrontController::class, 'product'])->name('storefront.product');

    // Clientes ecommerce (auth + cuenta) — Fase 9.
    require __DIR__.'/storefront.php';
});

// Descarga de medios privados mediante URL firmada (productos descargables, etc.).
Route::get('media/{media}/download', [MediaController::class, 'download'])
    ->name('media.download')
    ->middleware('signed');

// Notificaciones de pago (server-to-server). Sin auth ni CSRF; cada pasarela
// valida la autenticidad de la notificación.
Route::post('webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payments');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

// Catálogo bajo prefijo de tienda (multisitio por path, p. ej. /sports/...).
// Se registra al final y con una restricción que excluye los segmentos
// reservados, para no capturar /admin, /cuenta, /login, etc.
Route::middleware('resolve.store')->prefix('{store_code}')->name('storefront.store.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('c/{slug}', [StorefrontController::class, 'category'])->name('category');
    Route::get('p/{slug}', [StorefrontController::class, 'product'])->name('product');
})->where('store_code', '(?!admin|cuenta|carrito|checkout|login|register|logout|forgot-password|reset-password|dashboard|settings|storage|media|webhooks|up|user|two-factor-challenge|email|api|sanctum|build|c|p)[a-z0-9-]+');
