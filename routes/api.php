<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('search')->group(function () {
    Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    Route::post('rates', [SearchController::class, 'searchRates']);
});

Route::get('public/popular-routes', [SearchController::class, 'popularRoutes']);
