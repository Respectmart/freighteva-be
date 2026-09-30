<?php

namespace Tests\Feature;

use App\Models\MerchantBookingException;
use App\Models\MerchantEndorsement;
use App\Models\MerchantPerformanceLog;
use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerchantExceptionEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $merchantA;
    protected Tenant $merchantB;
    protected Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Merchant A (Primary)
        $this->merchantA = Tenant::firstOrCreate(
            ['name' => 'Atlantic Direct Freight'],
            [
                'email' => 'atlantic@example.com',
                'company_slug' => 'atlantic-direct-freight',
                'status' => 'active',
                'active' => true,
                'company_name' => 'Atlantic Direct Freight Corp',
                'is_verified' => true,
                'rating_score' => 4.8,
                'rating_avg' => 4.8,
                'rating_count' => 85,
            ]
        );

        MerchantEndorsement::updateOrInsert(
            ['tenant_id' => $this->merchantA->id],
            [
                'is_endorsed' => true,
                'status' => 'ENDORSED',
                'tier' => 4,
                'endorsed_at' => now(),
            ]
        );

        // 2. Setup Merchant B (Alternative Endorsed Partner)
        $this->merchantB = Tenant::firstOrCreate(
            ['name' => 'Global Horizon Logistics'],
            [
                'email' => 'horizon@example.com',
                'company_slug' => 'global-horizon-logistics',
                'status' => 'active',
                'active' => true,
                'company_name' => 'Global Horizon Logistics Ltd',
                'is_verified' => true,
                'rating_score' => 4.7,
                'rating_avg' => 4.7,
                'rating_count' => 60,
            ]
        );

        MerchantEndorsement::updateOrInsert(
            ['tenant_id' => $this->merchantB->id],
            [
                'is_endorsed' => true,
                'status' => 'ENDORSED',
                'tier' => 4,
                'endorsed_at' => now(),
            ]
        );

        // Ensure both merchants have active routes
        DB::table('shipment_routes')->updateOrInsert(
            [
                'tenant_id' => $this->merchantA->id,
                'sending_country_id' => 1,
                'receiving_country_id' => 2,
            ],
            [
                'shipping_rate' => 6.50,
                'insurance' => 0.50,
                'insurance_type' => 'fixed',
                'default_metric_unit' => 'kg',
                'default_currency' => 'USD',
                'max_no_phases' => 2,
                'min_delivery_day' => 4,
                'max_delivery_day' => 6,
                'mode' => 'air',
            ]
        );

        DB::table('shipment_routes')->updateOrInsert(
            [
                'tenant_id' => $this->merchantB->id,
                'sending_country_id' => 1,
                'receiving_country_id' => 2,
            ],
            [
                'shipping_rate' => 7.20,
                'insurance' => 0.50,
                'insurance_type' => 'fixed',
                'default_metric_unit' => 'kg',
                'default_currency' => 'USD',
                'max_no_phases' => 2,
                'min_delivery_day' => 5,
                'max_delivery_day' => 7,
                'mode' => 'air',
            ]
        );

        // 3. Create Booking Assigned to Merchant A
        $this->shipment = Shipment::create([
            'tenant_id' => $this->merchantA->id,
            'origin' => 'Afghanistan',
            'destination' => 'Aland Islands',
            'weight' => 10.0,
            'mode' => 'air',
            'carrier' => $this->merchantA->company_name,
            'status' => 'booked',
            'payment_status' => 1,
            'invoice_prefix' => 'EVA-INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'EVA-REJ-' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 1,
            'amount' => 165.00,
            'total_amount' => 180.00,
            'currency' => 'USD',
            'insurance_value' => 15.00,
            'handling_fee' => 0.00,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time_slot' => '09:00 AM - 01:00 PM',
            'pickup_type' => 'Door-to-door',
            'freight_mode' => 'air',
            'user_id' => 1,
        ]);
    }

    /**
     * Test Merchant Rejection triggers the Exception Engine and automatically reassigns to Alternative Partner.
     */
    public function test_rejection_triggers_exception_engine_and_auto_reassigns(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchantA->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/reject", [
                'reason_code' => 'CAPACITY_FULL',
                'reason_notes' => 'Air cargo space booked out for this week departure.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reassigned' => true,
                'shipment_status' => 'reassigned',
            ])
            ->assertJsonStructure([
                'success',
                'reassigned',
                'message',
                'exception_id',
                'reassigned_to' => [
                    'merchant_id',
                    'company_name',
                ],
            ]);

        // 1. Verify Exception Record was created
        $exception = MerchantBookingException::where('shipment_id', $this->shipment->id)->first();
        $this->assertNotNull($exception);
        $this->assertEquals($this->merchantA->id, $exception->original_tenant_id);
        $this->assertEquals('CAPACITY_FULL', $exception->reason_code);
        $this->assertEquals('REASSIGNED', $exception->resolution_status);
        $this->assertTrue($exception->penalty_applied);

        // 2. Verify Penalty Logged for Rejecting Merchant
        $penaltyLog = MerchantPerformanceLog::where('tenant_id', $this->merchantA->id)
            ->where('metric_type', 'rejection_penalty')
            ->first();
        $this->assertNotNull($penaltyLog);
        $this->assertEquals(-5.0, $penaltyLog->impact_score);

        // 3. Verify Shipment Reassignment
        $refreshedShipment = $this->shipment->fresh();
        $this->assertEquals('reassigned', $refreshedShipment->status);
        $this->assertNotEquals($this->merchantA->id, $refreshedShipment->tenant_id);

        // 4. Verify Tracking Event logged
        $reassignedEvent = $refreshedShipment->trackingEvents()
            ->where('status', 'EXCEPTION_REASSIGNED')
            ->first();
        $this->assertNotNull($reassignedEvent);
        $this->assertEquals('Fulfillment Partner Reassigned', $reassignedEvent->title);
    }

    /**
     * Test Unauthorized Merchant Cannot Reject Another Merchant's Booking.
     */
    public function test_unauthorized_merchant_cannot_reject_others_booking(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchantB->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/reject", [
                'reason_code' => 'SCHEDULE_CONFLICT',
            ]);

        $response->assertStatus(403);
    }
}
