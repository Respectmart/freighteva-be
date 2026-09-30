<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audit\MarketplaceAuditService;
use App\Services\Recommendation\PartnerRecommendationEngine;
use App\Services\Recommendation\QuoteTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected PartnerRecommendationEngine $recommendationEngine;
    protected QuoteTokenService $tokenService;
    protected MarketplaceAuditService $auditService;

    public function __construct(
        PartnerRecommendationEngine $recommendationEngine,
        QuoteTokenService $tokenService,
        MarketplaceAuditService $auditService
    ) {
        $this->recommendationEngine = $recommendationEngine;
        $this->tokenService = $tokenService;
        $this->auditService = $auditService;
    }

    /**
     * Get top curated Freighteva-branded recommendations with HMAC-signed booking tokens.
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $request->validate([
            'origin_country_id' => 'required|integer',
            'destination_country_id' => 'required|integer',
            'mode' => 'nullable|string|in:air,ocean',
            'weight_kg' => 'nullable|numeric|min:0.1',
            'origin_city' => 'nullable|string',
            'destination_city' => 'nullable|string',
        ]);

        $params = [
            'origin_country_id' => (int)$request->input('origin_country_id'),
            'destination_country_id' => (int)$request->input('destination_country_id'),
            'mode' => $request->input('mode', 'air'),
            'weight_kg' => (float)$request->input('weight_kg', 1.0),
            'origin_city' => $request->input('origin_city'),
            'destination_city' => $request->input('destination_city'),
        ];

        $result = $this->recommendationEngine->getRecommendations($params);

        // Log Quote Generation to Audit Trail
        $this->auditService->logQuoteGenerated(
            searchParams: $params,
            totalQuotes: $result['total_recommended'] ?? 0,
            eligibleCount: $result['diagnostics']['eligible_count'] ?? 0,
            diagnostics: $result['diagnostics'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Validate an HMAC-signed booking token before initiating checkout.
     */
    public function verifyQuoteToken(Request $request): JsonResponse
    {
        $request->validate([
            'booking_token' => 'required|string',
        ]);

        $verification = $this->tokenService->verifyToken($request->input('booking_token'));

        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'reason' => $verification['reason'],
                'message' => $this->getVerificationErrorMessage($verification['reason']),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'quote' => $verification['payload'],
        ]);
    }

    /**
     * Human-friendly error messages for token verification failures.
     */
    protected function getVerificationErrorMessage(string $reason): string
    {
        return match ($reason) {
            'QUOTE_EXPIRED' => 'This rate quotation has expired after 15 minutes. Please refresh for a live updated rate.',
            'SIGNATURE_VERIFICATION_FAILED' => 'Invalid security signature. The quote rate or metadata may have been modified.',
            'MALFORMED_TOKEN_STRUCTURE' => 'Invalid token format.',
            default => 'Quote validation failed. Please request a new quotation.',
        };
    }
}
