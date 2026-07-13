<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::prefix('search')->group(function () {
    Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    Route::post('rates', [SearchController::class, 'searchRates']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('shipments/{shipment}/reviews', [ReviewController::class, 'store']);
    Route::get('shipments', [ShipmentController::class, 'index']);
    Route::post('shipments', [ShipmentController::class, 'store']);
});

Route::get('tenants/{tenant}/reviews', [ReviewController::class, 'index']);
Route::get('public/shipments/{shipment}', [ShipmentController::class, 'showPublic']);
Route::post('public/shipments/{shipment}/reviews', [ReviewController::class, 'storePublic']);
Route::get('public/popular-routes', [SearchController::class, 'popularRoutes']);
