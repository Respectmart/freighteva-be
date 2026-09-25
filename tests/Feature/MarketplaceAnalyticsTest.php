<?php

namespace Tests\Feature;

use App\Models\MerchantBookingException;
use App\Models\MerchantEndorsement;
use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MarketplaceAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $merchant;
    protected Shipment $shipment1;
    protected Shipment $shipment2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Tenant::firstOrCreate(
            ['name' => 'Analytics Apex Freight'],
            [
                'email' => 'apex@analytics.com',
                'company_slug' => 'analytics-apex-freight',
                'status' => 'active',
                'active' => true,
                'company_name' => 'Apex Freight Global',
                'rating_score' => 4.9,
            ]
        );

        MerchantEndorsement::updateOrInsert(
            ['tenant_id' => $this->merchant->id],
            [
                'is_endorsed' => true,
                'status' => 'ENDORSED',
                'tier' => 5,
                'endorsed_at' => now(),
            ]
        );

        // Seed sample shipments
        $this->shipment1 = Shipment::create([
            'tenant_id' => $this->merchant->id,
            'origin' => 'Washington, DC, US',
            'destination' => 'Lagos, Nigeria',
            'weight' => 12.0,
            'mode' => 'air',
            'carrier' => $this->merchant->company_name,
            'status' => 'delivered',
            'payment_status' => 1,
            'invoice_prefix' => 'EVA-INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'EVA-ANL-' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 1,
            'amount' => 180.00,
            'total_amount' => 200.00,
            'currency' => 'USD',
            'insurance_value' => 20.00,
            'handling_fee' => 0.00,
            'freight_mode' => 'air',
            'user_id' => 1,
        ]);

        $this->shipment2 = Shipment::create([
            'tenant_id' => $this->merchant->id,
            'origin' => 'London, UK',
            'destination' => 'Accra, Ghana',
            'weight' => 50.0,
            'mode' => 'ocean',
            'carrier' => $this->merchant->company_name,
            'status' => 'in_transit',
            'payment_status' => 1,
            'invoice_prefix' => 'EVA-INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'EVA-ANL-' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 2,
            'amount' => 450.00,
            'total_amount' => 500.00,
            'currency' => 'USD',
            'insurance_value' => 50.00,
            'handling_fee' => 0.00,
            'freight_mode' => 'ocean',
            'user_id' => 1,
        ]);
    }

    /**
     * Test Platform Admin Analytics Overview API.
     */
    public function test_admin_analytics_overview_returns_gross_metrics_and_funnel(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'gross_metrics' => [
                        'total_bookings',
                        'gross_marketplace_volume',
                        'average_order_value',
                        'currency',
                    ],
                    'status_distribution',
                    'mode_distribution',
                    'funnel' => [
                        'quotes_generated',
                        'bookings_created',
                        'deliveries_completed',
                        'quote_to_booking_conversion_rate',
                        'fulfillment_rate',
                    ],
                    'exception_metrics' => [
                        'total_rejections',
                        'reassigned_count',
                        'unfulfillable_count',
                        'reassignment_success_rate',
                        'rejection_reasons',
                    ],
                    'top_corridors',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['gross_metrics']['total_bookings']);
        $this->assertGreaterThanOrEqual(700.00, $data['gross_metrics']['gross_marketplace_volume']);
    }

    /**
     * Test Trade Corridors Analytics API.
     */
    public function test_admin_analytics_corridors_returns_corridor_breakdown(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/corridors');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_corridors',
                    'corridors' => [
                        '*' => [
                            'route',
                            'mode',
                            'shipments_count',
                            'total_revenue',
                            'average_shipment_price',
                            'total_weight_kg',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test Merchant Performance Leaderboard API.
     */
    public function test_admin_analytics_merchants_leaderboard(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/merchants');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_merchants',
                    'leaderboard' => [
                        '*' => [
                            'merchant_id',
                            'company_name',
                            'is_endorsed',
                            'endorsement_tier',
                            'rating',
                            'total_shipments',
                            'total_revenue',
                            'acceptance_rate',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test Merchant Individual Analytics Overview.
     */
    public function test_merchant_analytics_overview_returns_merchant_kpis(): void
    {
        $response = $this->withHeaders(['X-Merchant-ID' => $this->merchant->id])
            ->getJson('/api/v1/merchant/analytics/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'merchant_info' => [
                        'tenant_id' => $this->merchant->id,
                        'is_endorsed' => true,
                        'endorsement_tier' => 5,
                    ],
                    'kpis' => [
                        'total_bookings' => 2,
                        'total_revenue' => 700.00,
                        'delivered_shipments' => 1,
                        'active_shipments' => 1,
                    ],
                ],
            ]);
    }
}
