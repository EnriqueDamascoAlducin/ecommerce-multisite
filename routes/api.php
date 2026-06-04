<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

// API pública/headless versionada (Fase 24). Catálogo de solo lectura sin auth;
// datos del cliente (órdenes) protegidos por token Sanctum.
Route::prefix('v1')->group(function () {
    // Catálogo público (scope por `?store=<code>` o tienda por defecto).
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);

    // Autenticación de cliente por token.
    Route::post('login', [AuthController::class, 'login']);

    // Endpoints autenticados (token del cliente).
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
    });
});
