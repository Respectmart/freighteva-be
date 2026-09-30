<?php

namespace Tests\Feature;

use App\Models\MerchantEndorsement;
use App\Models\Tenant;
use App\Services\Recommendation\MerchantEligibilityService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerchantEligibilityTest extends TestCase
{
    protected MerchantEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibilityService = app(MerchantEligibilityService::class);
    }

    /**
     * Test Merchant Eligibility Service Hard Gates.
     */
    public function test_unendorsed_merchant_is_excluded_by_hard_gate(): void
    {
        $tenant = Tenant::with('endorsement')->first();
        if (!$tenant) {
            $this->markTestSkipped('No tenants found in test database.');
        }

        // Set endorsement to false
        MerchantEndorsement::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['is_endorsed' => false, 'status' => 'PENDING']
        );
        $tenant->load('endorsement');

        $evaluation = $this->eligibilityService->evaluateSingleTenant($tenant, 1, 2, 'air', 10.0);

        $this->assertFalse($evaluation['is_eligible']);
        $this->assertEquals(MerchantEligibilityService::REASON_NOT_ENDORSED, $evaluation['reason_code']);
    }

    public function test_suspended_merchant_is_excluded(): void
    {
        $tenant = Tenant::with('endorsement')->first();
        if (!$tenant) {
            $this->markTestSkipped('No tenants found in test database.');
        }

        MerchantEndorsement::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['is_endorsed' => true, 'status' => 'SUSPENDED']
        );
        $tenant->load('endorsement');

        $evaluation = $this->eligibilityService->evaluateSingleTenant($tenant, 1, 2, 'air', 10.0);

        $this->assertFalse($evaluation['is_eligible']);
        $this->assertEquals(MerchantEligibilityService::REASON_ENDORSEMENT_SUSPENDED, $evaluation['reason_code']);
    }

    public function test_expired_endorsement_is_excluded(): void
    {
        $tenant = Tenant::with('endorsement')->first();
        if (!$tenant) {
            $this->markTestSkipped('No tenants found in test database.');
        }

        MerchantEndorsement::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'is_endorsed' => true,
                'status' => 'ENDORSED',
                'expires_at' => now()->subDays(5),
            ]
        );
        $tenant->load('endorsement');

        $evaluation = $this->eligibilityService->evaluateSingleTenant($tenant, 1, 2, 'air', 10.0);

        $this->assertFalse($evaluation['is_eligible']);
        $this->assertEquals(MerchantEligibilityService::REASON_ENDORSEMENT_EXPIRED, $evaluation['reason_code']);
    }

    /**
     * Test Admin API Endpoints for Merchant Endorsements.
     */
    public function test_admin_merchants_index_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/v1/admin/merchants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'current_page',
                'total',
            ],
        ]);
    }

    public function test_admin_can_update_merchant_endorsement(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->markTestSkipped('No tenants found in test database.');
        }

        $response = $this->postJson("/api/v1/admin/merchants/{$tenant->id}/endorse", [
            'is_endorsed' => true,
            'endorsement_status' => 'ENDORSED',
            'endorsement_tier' => 5,
            'endorsement_notes' => 'Verified top-tier freight carrier.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.endorsement.is_endorsed', true);
        $response->assertJsonPath('data.endorsement.status', 'ENDORSED');
        $response->assertJsonPath('data.endorsement.tier', 5);
    }

    public function test_admin_eligibility_check_endpoint(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->markTestSkipped('No tenants found in test database.');
        }

        $response = $this->getJson("/api/v1/admin/merchants/{$tenant->id}/eligibility-check?origin_country_id=1&destination_country_id=2&mode=air&weight_kg=15");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'merchant_id',
            'company_name',
            'evaluation' => [
                'is_eligible',
            ],
        ]);
    }
}
