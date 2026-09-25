<?php

namespace Tests\Feature;

use App\Models\MarketplaceAuditLog;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Audit\MarketplaceAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MarketplaceAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $merchant;
    protected Shipment $shipment;
    protected MarketplaceAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(MarketplaceAuditService::class);

        $this->merchant = Tenant::firstOrCreate(
            ['name' => 'Audit Partner Logistics'],
            [
                'email' => 'audit@partner.com',
                'company_slug' => 'audit-partner-logistics',
                'status' => 'active',
                'active' => true,
                'company_name' => 'Audit Partner Logistics Inc',
            ]
        );

        $this->shipment = Shipment::create([
            'tenant_id' => $this->merchant->id,
            'origin' => 'Washington, DC, US',
            'destination' => 'Lagos, Nigeria',
            'weight' => 10.0,
            'mode' => 'air',
            'carrier' => $this->merchant->company_name,
            'status' => 'booked',
            'payment_status' => 1,
            'invoice_prefix' => 'EVA-INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'EVA-AUD-' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 1,
            'amount' => 150.00,
            'total_amount' => 165.00,
            'currency' => 'USD',
            'insurance_value' => 15.00,
            'handling_fee' => 0.00,
            'freight_mode' => 'air',
            'user_id' => 1,
        ]);
    }

    /**
     * Test Audit Service records booking events.
     */
    public function test_audit_service_records_booking_events(): void
    {
        $log = $this->auditService->logBookingCreated($this->shipment, [
            'service_name' => 'Freighteva Priority Express',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals(MarketplaceAuditService::EVENT_BOOKING_CREATED, $log->event_type);
        $this->assertEquals($this->shipment->id, $log->entity_id);
        $this->assertEquals($this->merchant->id, $log->tenant_id);
    }

    /**
     * Test Merchant CRM Acknowledgment writes to Audit Trail.
     */
    public function test_merchant_crm_acknowledgment_logs_audit_trail(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->postJson("/api/v1/merchant/bookings/{$this->shipment->id}/acknowledge");

        $response->assertStatus(200);

        $auditLog = MarketplaceAuditLog::where('entity_id', $this->shipment->id)
            ->where('event_type', MarketplaceAuditService::EVENT_MERCHANT_ACKNOWLEDGED)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->merchant->id, $auditLog->tenant_id);
        $this->assertEquals('merchant', $auditLog->actor_type);
    }

    /**
     * Test Admin Audit Logs API retrieval and filtering.
     */
    public function test_admin_audit_logs_api_with_filters(): void
    {
        // Seed an audit log
        $this->auditService->record(
            eventType: 'endorsement_updated',
            entityType: 'tenant',
            entityId: $this->merchant->id,
            payload: ['status' => 'ENDORSED'],
            tenantId: $this->merchant->id,
            actorType: 'admin'
        );

        $response = $this->getJson("/api/v1/admin/audit-logs?event_type=endorsement_updated&tenant_id={$this->merchant->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'actor_type',
                            'event_type',
                            'entity_type',
                            'entity_id',
                            'payload',
                            'created_at',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }
}
