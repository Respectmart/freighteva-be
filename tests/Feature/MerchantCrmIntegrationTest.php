<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MerchantCrmIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $merchant;
    protected Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Test Merchant
        $this->merchant = Tenant::firstOrCreate(
            ['name' => 'Swift Cargo Express'],
            [
                'email' => 'swift@example.com',
                'company_slug' => 'swift-cargo-express',
                'status' => 'active',
                'active' => true,
                'company_name' => 'Swift Cargo Express LLC',
                'is_verified' => true,
                'rating_score' => 4.9,
                'rating_avg' => 4.9,
                'rating_count' => 120,
            ]
        );

        // 2. Create Inbound Marketplace Booking
        $this->shipment = Shipment::create([
            'tenant_id' => $this->merchant->id,
            'origin' => 'Washington, DC, United States',
            'destination' => 'Lagos, Nigeria',
            'weight' => 5.0,
            'mode' => 'air',
            'carrier' => $this->merchant->company_name,
            'status' => 'booked',
            'payment_status' => 1,
            'invoice_prefix' => 'EVA-INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'EVA-TEST-' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 1,
            'amount' => 125.00,
            'total_amount' => 140.00,
            'currency' => 'USD',
            'insurance_value' => 15.00,
            'handling_fee' => 0.00,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time_slot' => '09:00 AM - 01:00 PM',
            'pickup_type' => 'Door-to-door',
            'freight_mode' => 'air',
            'user_id' => 1,
        ]);

        $this->shipment->recordTrackingEvent(
            status: 'BOOKED',
            title: 'Booking Confirmed',
            description: 'Shipment booking confirmed on Freighteva.',
            location: $this->shipment->origin,
            actorType: 'customer'
        );
    }

    /**
     * Test Merchant CRM can list inbound marketplace bookings.
     */
    public function test_merchant_can_list_inbound_bookings(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->getJson('/api/v1/merchant/bookings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'merchant' => [
                    'id' => $this->merchant->id,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'merchant' => ['id', 'name', 'company_name'],
                'data',
                'pagination' => ['total', 'current_page', 'per_page', 'last_page'],
            ]);

        $this->assertTrue(collect($response->json('data'))->contains('id', $this->shipment->id));
    }

    /**
     * Test Merchant can view single booking details.
     */
    public function test_merchant_can_view_booking_details(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->getJson("/api/v1/merchant/bookings/{$this->shipment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->shipment->id,
                    'awb_number' => $this->shipment->awb_number,
                    'status' => 'booked',
                    'payment_status' => 'pending',
                    'total_amount' => 140,
                ],
            ]);
    }

    /**
     * Test Merchant can acknowledge receipt in CRM.
     */
    public function test_merchant_can_acknowledge_booking(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/acknowledge");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'merchant_received',
            ]);

        $this->assertEquals('merchant_received', $this->shipment->fresh()->status);
        $this->assertTrue($this->shipment->fresh()->trackingEvents->contains('status', 'MERCHANT_RECEIVED'));
    }

    /**
     * Test Merchant can accept booking for fulfillment.
     */
    public function test_merchant_can_accept_booking(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'accepted',
            ]);

        $this->assertEquals('accepted', $this->shipment->fresh()->status);
        $this->assertTrue($this->shipment->fresh()->trackingEvents->contains('status', 'ACCEPTED'));
    }

    /**
     * Test Merchant can advance shipment lifecycle to DELIVERED.
     */
    public function test_merchant_can_update_lifecycle_to_delivered(): void
    {
        // 1. Pickup Scheduled
        $res1 = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/status", [
                'status' => 'pickup_scheduled',
                'location' => 'Washington Depot',
            ]);
        $res1->assertStatus(200);

        // 2. Picked Up
        $res2 = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/status", [
                'status' => 'picked_up',
                'location' => 'Washington Airport Hub (IAD)',
            ]);
        $res2->assertStatus(200);

        // 3. In Transit
        $res3 = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/status", [
                'status' => 'in_transit',
                'location' => 'Airway Route IAD-LOS Flight #924',
            ]);
        $res3->assertStatus(200);

        // 4. Delivered
        $res4 = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/status", [
                'status' => 'delivered',
                'location' => 'Victoria Island, Lagos, Nigeria',
                'description' => 'Cargo handed over to recipient. Sign-off recorded.',
            ]);
        $res4->assertStatus(200);

        $this->assertEquals('delivered', $this->shipment->fresh()->status);
    }

    /**
     * Test Public Tracking API returns full milestone progress.
     */
    public function test_public_tracking_endpoint_returns_complete_milestones(): void
    {
        // Add step to test tracking timeline
        $this->shipment->recordTrackingEvent(
            status: 'IN_TRANSIT',
            title: 'In Transit',
            description: 'Departed sorting facility.',
            location: 'Washington Hub',
            actorType: 'merchant'
        );
        $this->shipment->update(['status' => 'in_transit']);

        $response = $this->getJson("/api/v1/shipments/track/{$this->shipment->awb_number}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'awb_number' => $this->shipment->awb_number,
                    'status' => 'IN_TRANSIT',
                    'progress_percentage' => 80,
                    'is_delivered' => false,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'awb_number',
                    'invoice_number',
                    'status',
                    'progress_percentage',
                    'milestones',
                    'timeline',
                ],
            ]);
    }
}
