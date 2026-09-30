<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('search')->group(function () {
    Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    Route::get('smart-location', [\App\Http\Controllers\Api\V1\SmartLocationController::class, 'resolve']);
    Route::post('rates', [SearchController::class, 'searchRates']);
});

Route::get('public/popular-routes', [SearchController::class, 'popularRoutes']);

// Shipments API
Route::get('shipments', [\App\Http\Controllers\ShipmentController::class, 'index']);
Route::post('shipments', [\App\Http\Controllers\ShipmentController::class, 'store']);
Route::get('shipments/{id}', [\App\Http\Controllers\ShipmentController::class, 'show']);
Route::get('public/shipments/{shipment}', [\App\Http\Controllers\ShipmentController::class, 'showPublic']);

// Reviews & Ratings API
Route::get('reviews/{tenant_id}', [\App\Http\Controllers\ReviewController::class, 'index']);
Route::get('tenants/{tenant_id}/reviews', [\App\Http\Controllers\ReviewController::class, 'index']);
Route::post('reviews', [\App\Http\Controllers\ReviewController::class, 'store']);
Route::post('shipments/{shipment}/reviews', [\App\Http\Controllers\ReviewController::class, 'store']);
Route::post('public/shipments/{shipment}/reviews', [\App\Http\Controllers\ReviewController::class, 'storePublic']);

// Freighteva Location-Aware Freight Routing API & Intelligent Recommendations (PRD Requirement 09)
Route::prefix('v1')->group(function () {
    Route::post('/routing/match-merchants', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'matchMerchants'])->name('api.v1.routing.match');

    // 5-Partner Recommendation Engine & Rate Locks
    Route::prefix('recommendations')->group(function () {
        Route::post('/quotes', [\App\Http\Controllers\Api\V1\RecommendationController::class, 'getRecommendations'])->name('api.v1.recommendations.quotes');
        Route::post('/verify-token', [\App\Http\Controllers\Api\V1\RecommendationController::class, 'verifyQuoteToken'])->name('api.v1.recommendations.verify-token');
    });

    // Unified In-Platform Booking Journey (Module 4)
    Route::prefix('bookings')->group(function () {
        Route::post('/verify-quote', [\App\Http\Controllers\Api\V1\UnifiedBookingController::class, 'verifyQuote'])->name('api.v1.bookings.verify');
        Route::post('/create', [\App\Http\Controllers\Api\V1\UnifiedBookingController::class, 'createBooking'])->name('api.v1.bookings.create');
    });

    // Stripe Payments Integration
    Route::prefix('payments')->group(function () {
        Route::get('/config', [\App\Http\Controllers\Api\V1\StripePaymentController::class, 'config'])->name('api.v1.payments.config');
        Route::post('/create-intent', [\App\Http\Controllers\Api\V1\StripePaymentController::class, 'createIntent'])->name('api.v1.payments.create-intent');
    });

    // Admin Endorsement, Analytics & Audit Trail APIs (Module 1 & Module 6)
    Route::prefix('admin')->group(function () {
        Route::get('/merchants', [\App\Http\Controllers\Api\V1\Admin\MerchantEndorsementController::class, 'index']);
        Route::post('/merchants/{id}/endorse', [\App\Http\Controllers\Api\V1\Admin\MerchantEndorsementController::class, 'endorse']);
        Route::get('/merchants/{id}/eligibility-check', [\App\Http\Controllers\Api\V1\Admin\MerchantEndorsementController::class, 'eligibilityCheck']);

        // Module 6: Executive Analytics & Audit Trail
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [\App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController::class, 'overview'])->name('api.v1.admin.analytics.overview');
            Route::get('/corridors', [\App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController::class, 'corridors'])->name('api.v1.admin.analytics.corridors');
            Route::get('/merchants', [\App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController::class, 'merchants'])->name('api.v1.admin.analytics.merchants');
        });
        Route::get('/audit-logs', [\App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController::class, 'auditLogs'])->name('api.v1.admin.audit-logs');
    });

    // Merchant CRM Integration & Performance Analytics (Module 5 & Module 6)
    Route::prefix('merchant')->group(function () {
        Route::get('/bookings', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'index'])->name('api.v1.merchant.bookings.index');
        Route::get('/bookings/{id}', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'show'])->name('api.v1.merchant.bookings.show');
        Route::post('/bookings/{id}/acknowledge', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'acknowledge'])->name('api.v1.merchant.bookings.acknowledge');
        Route::post('/bookings/{id}/accept', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'accept'])->name('api.v1.merchant.bookings.accept');
        Route::post('/bookings/{id}/status', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'updateStatus'])->name('api.v1.merchant.bookings.status');
        Route::post('/bookings/{id}/reject', [\App\Http\Controllers\Api\V1\Merchant\MerchantCrmController::class, 'reject'])->name('api.v1.merchant.bookings.reject');

        // Module 6: Merchant Performance KPIs
        Route::get('/analytics/overview', [\App\Http\Controllers\Api\V1\Merchant\MerchantAnalyticsController::class, 'overview'])->name('api.v1.merchant.analytics.overview');
    });

    // Public & Customer Shipment Tracking
    Route::get('/shipments/track/{awb}', [\App\Http\Controllers\Api\V1\TrackingController::class, 'track'])->name('api.v1.shipments.track');
});

// Swagger Interactive API Documentation
Route::get('/documentation', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'swaggerUi'])->name('api.documentation');
Route::get('/docs/openapi.json', [\App\Http\Controllers\Api\V1\FreightevaRoutingController::class, 'swaggerSpec'])->name('api.docs.openapi');
