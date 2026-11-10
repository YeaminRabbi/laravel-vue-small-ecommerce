<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('storefront/shops', [StorefrontController::class, 'shops'])->middleware('throttle:60,1');
    Route::get('storefront', StorefrontController::class)->middleware('throttle:60,1');
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view');
        Route::get('shops', [ShopController::class, 'index'])->middleware('can:shops.view');
        Route::apiResource('shops', ShopController::class)->only(['store', 'update', 'destroy'])->middleware('can:shops.manage');
        Route::apiResource('owners', OwnerController::class)->except('show')->middleware('can:owners.manage');
        Route::get('products', [ProductController::class, 'index'])->middleware('can:catalog.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('can:catalog.create');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('can:catalog.create');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('can:catalog.create');
        Route::get('shops/{shop}/listings', [ListingController::class, 'index'])->middleware('can:listings.manage');
        Route::post('shops/{shop}/listings', [ListingController::class, 'store'])->middleware('can:listings.manage');
        Route::put('shops/{shop}/listings/{listing}', [ListingController::class, 'update'])->middleware('can:listings.manage');
        Route::delete('shops/{shop}/listings/{listing}', [ListingController::class, 'destroy'])->middleware('can:listings.manage');
    });
});
