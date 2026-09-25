<?php

namespace Tests\Feature;

use App\Models\MerchantEndorsement;
use App\Models\Tenant;
use App\Services\Recommendation\PartnerRecommendationEngine;
use App\Services\Recommendation\QuoteTokenService;
use Tests\TestCase;

class PartnerRecommendationTest extends TestCase
{
    protected PartnerRecommendationEngine $engine;
    protected QuoteTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(PartnerRecommendationEngine::class);
        $this->tokenService = app(QuoteTokenService::class);
    }

    /**
     * Test Public Recommendation Quotes API endpoint returns curated partner recommendations.
     */
    public function test_recommendation_quotes_endpoint_success(): void
    {
        $response = $this->postJson(route('api.v1.recommendations.quotes'), [
            'origin_country_id' => 232,
            'destination_country_id' => 233,
            'mode' => 'air',
            'weight_kg' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'recommendations' => [
                    '*' => [
                        'service_name',
                        'service_key',
                        'badge',
                        'calculated_price',
                        'transit_days',
                        'booking_token',
                        'quote_id',
                        'expires_at',
                        'scores' => [
                            'composite',
                            'speed',
                            'cost',
                            'trust',
                            'convenience',
                            'flexibility',
                        ],
                        'partner_attribution' => [
                            'merchant_id',
                            'company_name',
                            'is_verified',
                            'is_endorsed',
                        ],
                    ],
                ],
                'total_recommended',
                'quote_ttl_minutes',
                'diagnostics' => [
                    'total_evaluated',
                    'eligible_count',
                    'excluded',
                ],
            ],
        ]);
    }

    /**
     * Test Validation on missing mandatory params.
     */
    public function test_recommendation_quotes_validation_failure(): void
    {
        $response = $this->postJson(route('api.v1.recommendations.quotes'), [
            'weight_kg' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['origin_country_id', 'destination_country_id']);
    }

    /**
     * Test HMAC Quote Token Generation & Verification.
     */
    public function test_quote_token_generation_and_verification(): void
    {
        $payload = [
            'tenant_id' => 2,
            'route_id' => 4,
            'calculated_price' => 155.0,
            'mode' => 'air',
            'origin_country_id' => 232,
            'destination_country_id' => 233,
            'weight_kg' => 10.0,
            'service_name' => 'Freighteva Swift',
        ];

        $tokenData = $this->tokenService->generateToken($payload, 15);
        $this->assertNotEmpty($tokenData['token']);
        $this->assertGreaterThan(time(), $tokenData['expires_at']);

        // Verify via Token Service
        $verification = $this->tokenService->verifyToken($tokenData['token']);
        $this->assertTrue($verification['valid']);
        $this->assertEquals(155.0, $verification['payload']['calculated_price']);
        $this->assertEquals(2, $verification['payload']['tenant_id']);
    }

    /**
     * Test Tampered Token Signature Rejection.
     */
    public function test_tampered_quote_token_is_rejected(): void
    {
        $payload = [
            'tenant_id' => 2,
            'calculated_price' => 155.0,
        ];

        $tokenData = $this->tokenService->generateToken($payload, 15);
        $originalToken = $tokenData['token'];

        // Tamper with payload (modify price from 155 to 10)
        [$encodedPayload, $signature] = explode('.', $originalToken);
        $tamperedJson = '{"tenant_id":2,"calculated_price":10.0,"expires_at":' . (time() + 900) . '}';
        $tamperedEncoded = rtrim(strtr(base64_encode($tamperedJson), '+/', '-_'), '=');
        $tamperedToken = $tamperedEncoded . '.' . $signature;

        $verification = $this->tokenService->verifyToken($tamperedToken);
        $this->assertFalse($verification['valid']);
        $this->assertEquals('SIGNATURE_VERIFICATION_FAILED', $verification['reason']);
    }

    /**
     * Test Expired Token Rejection.
     */
    public function test_expired_quote_token_is_rejected(): void
    {
        $payload = [
            'tenant_id' => 2,
            'calculated_price' => 155.0,
        ];

        // Generate token with -5 minutes TTL (already expired)
        $tokenData = $this->tokenService->generateToken($payload, -5);

        $verification = $this->tokenService->verifyToken($tokenData['token']);
        $this->assertFalse($verification['valid']);
        $this->assertEquals('QUOTE_EXPIRED', $verification['reason']);
    }

    /**
     * Test Token Verification API Endpoint.
     */
    public function test_verify_quote_token_api_endpoint(): void
    {
        $payload = [
            'tenant_id' => 2,
            'route_id' => 4,
            'calculated_price' => 155.0,
            'service_name' => 'Freighteva Swift',
        ];

        $tokenData = $this->tokenService->generateToken($payload, 15);

        $response = $this->postJson(route('api.v1.recommendations.verify-token'), [
            'booking_token' => $tokenData['token'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('quote.calculated_price', 155);
    }
}
