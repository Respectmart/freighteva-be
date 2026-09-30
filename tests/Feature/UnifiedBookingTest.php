<?php

namespace Tests\Feature;

use App\Services\Recommendation\QuoteTokenService;
use Tests\TestCase;

class UnifiedBookingTest extends TestCase
{
    protected QuoteTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = app(QuoteTokenService::class);
    }

    /**
     * Test Step 1: Verify quote rate lock token.
     */
    public function test_verify_quote_endpoint(): void
    {
        $tokenData = $this->tokenService->generateToken([
            'tenant_id' => 2,
            'calculated_price' => 155.0,
            'service_name' => 'Freighteva Swift',
            'weight_kg' => 10.0,
            'mode' => 'air',
        ], 15);

        $response = $this->postJson(route('api.v1.bookings.verify'), [
            'booking_token' => $tokenData['token'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('quote.service_name', 'Freighteva Swift');
    }

    /**
     * Test In-Platform 6-Step Booking Creation.
     */
    public function test_create_unified_booking_success(): void
    {
        $tokenData = $this->tokenService->generateToken([
            'tenant_id' => 2,
            'tenant_company' => 'enitan shipping inc',
            'calculated_price' => 155.0,
            'service_name' => 'Freighteva Swift',
            'currency' => 'GBP',
            'weight_kg' => 10.0,
            'mode' => 'air',
            'transit_days' => '3-5 days',
        ], 15);

        $payload = [
            'booking_token' => $tokenData['token'],
            'sender' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+44 7123 456789',
                'address' => '221B Baker Street',
                'city' => 'London',
                'postal_code' => 'NW1 6XE',
                'pickup_date' => now()->addDays(2)->toDateString(),
                'pickup_time_slot' => '10:00 AM - 02:00 PM',
            ],
            'receiver' => [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '+1 312 555 0199',
                'address' => '100 N Michigan Ave',
                'city' => 'Chicago',
                'postal_code' => '60601',
                'country' => 'United States',
            ],
            'cargo' => [
                'item_description' => 'Commercial samples and apparel',
                'declared_value' => 450.0,
                'package_type' => 'Box',
            ],
            'protection_plan' => 'premium', // +15.00
            'payment' => [
                'payment_method' => 'card',
            ],
        ];

        $response = $this->postJson(route('api.v1.bookings.create'), $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.service_name', 'Freighteva Swift');
        $response->assertJsonPath('data.base_price', 155);
        $response->assertJsonPath('data.protection_fee', 15);
        $response->assertJsonPath('data.total_paid', 170); // 155 + 15
        $response->assertJsonPath('data.status', 'CONFIRMED');
        $response->assertJsonPath('data.payment_status', 'PAID');
        $this->assertNotEmpty($response->json('data.awb_number'));
        $this->assertStringStartsWith('EVA-', $response->json('data.awb_number'));
    }

    /**
     * Test Validation on missing mandatory sender/receiver fields.
     */
    public function test_create_unified_booking_validation_errors(): void
    {
        $response = $this->postJson(route('api.v1.bookings.create'), [
            'booking_token' => 'some_token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sender.name', 'sender.email', 'sender.phone', 'sender.address', 'receiver.name', 'receiver.phone', 'receiver.address']);
    }
}
