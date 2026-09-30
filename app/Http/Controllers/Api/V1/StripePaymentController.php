<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Recommendation\QuoteTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripePaymentController extends Controller
{
    protected QuoteTokenService $tokenService;

    public function __construct(QuoteTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Get Stripe Public Key for Frontend Elements.
     */
    public function config(): JsonResponse
    {
        $publishableKey = config('services.stripe.key');

        return response()->json([
            'success' => true,
            'data' => [
                'publishable_key' => $publishableKey,
                'is_configured' => !empty($publishableKey) && !empty(config('services.stripe.secret')),
            ]
        ]);
    }

    /**
     * Create a Stripe PaymentIntent for locked booking rate.
     */
    public function createIntent(Request $request): JsonResponse
    {
        $request->validate([
            'booking_token' => 'required|string',
            'protection_plan' => 'nullable|string|in:basic,premium',
        ]);

        $stripeSecret = config('services.stripe.secret');
        if (empty($stripeSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured on this environment.',
            ], 500);
        }

        // 1. Verify Rate Lock Token
        $verification = $this->tokenService->verifyToken($request->input('booking_token'));
        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Rate lock has expired or is invalid. Please refresh quotes.',
                'reason' => $verification['reason'],
            ], 422);
        }

        $quote = $verification['payload'];

        // 2. Compute locked total
        $basePrice = (float)($quote['calculated_price'] ?? 0);
        $protectionPlan = $request->input('protection_plan', 'basic');
        $protectionFee = ($protectionPlan === 'premium') ? 15.00 : 0.00;
        $totalAmount = round($basePrice + $protectionFee, 2);
        $currency = strtolower($quote['currency'] ?? 'usd');

        // 3. Create Stripe PaymentIntent
        try {
            Stripe::setApiKey($stripeSecret);

            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($totalAmount * 100), // Stripe accepts amount in cents
                'currency' => $currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'description' => "Freighteva Shipment - " . ($quote['service_name'] ?? 'Freight Booking'),
                'metadata' => [
                    'service_name' => $quote['service_name'] ?? 'Freighteva Service',
                    'tenant_id' => $quote['tenant_id'] ?? 1,
                    'mode' => $quote['mode'] ?? 'air',
                    'weight_kg' => $quote['weight_kg'] ?? 1,
                    'protection_plan' => $protectionPlan,
                    'quote_id' => $quote['quote_id'] ?? '',
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $totalAmount,
                    'currency' => strtoupper($currency),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe payment initialization failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
