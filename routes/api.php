<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('search')->group(function () {
    Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    Route::post('rates', [SearchController::class, 'searchRates']);
});

Route::get('public/popular-routes', [SearchController::class, 'popularRoutes']);

// Shipments API
Route::get('shipments', [\App\Http\Controllers\ShipmentController::class, 'index']);
Route::post('shipments', [\App\Http\Controllers\ShipmentController::class, 'store']);
Route::get('shipments/{id}', [\App\Http\Controllers\ShipmentController::class, 'show']);

// Reviews & Ratings API
Route::get('reviews/{tenant_id}', [\App\Http\Controllers\ReviewController::class, 'index']);
Route::post('reviews', [\App\Http\Controllers\ReviewController::class, 'store']);

// Freighteva Location-Aware Freight Routing API
Route::prefix('v1')->group(function () {
    Route::post('/routing/match-merchants', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'matchMerchants'])->name('api.v1.routing.match');
});

// Swagger Interactive API Documentation
Route::get('/documentation', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'swaggerUi'])->name('api.documentation');
Route::get('/docs/openapi.json', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'swaggerSpec'])->name('api.docs.openapi');


