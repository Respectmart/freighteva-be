<?php

namespace Tests\Feature;

use App\Services\Routing\FreightevaRoutingEngine;
use Tests\TestCase;

class FreightevaRoutingTest extends TestCase
{
    protected FreightevaRoutingEngine $routingEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingEngine = app(FreightevaRoutingEngine::class);
    }

    /**
     * Test Hard Capability Gate: Non-serving destination is excluded.
     */
    public function test_hard_capability_filter_excludes_non_serving_destination(): void
    {
        $response = $this->postJson(route('api.v1.routing.match'), [
            'pickup_country' => 'US',
            'pickup_postcode' => '60601',
            'destination_country' => 'ZZ', // Non-existent destination
            'freight_mode' => 'air',
            'weight_kg' => 10,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('no_merchants_found', $data['match_status']);
        $this->assertNull($data['primary_merchant']);
    }

    /**
     * Test Validation on missing mandatory parameters.
     */
    public function test_validation_fails_on_missing_parameters(): void
    {
        $response = $this->postJson(route('api.v1.routing.match'), [
            'freight_mode' => 'air',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pickup_postcode', 'destination_country']);
    }

    /**
     * Test Swagger UI documentation endpoint.
     */
    public function test_swagger_documentation_endpoints(): void
    {
        $uiResponse = $this->get(route('api.documentation'));
        $uiResponse->assertStatus(200);
        $uiResponse->assertSee('Freighteva Global Logistics');
        $uiResponse->assertSee('swagger-ui');

        $specResponse = $this->get(route('api.docs.openapi'));
        $specResponse->assertStatus(200);
        $specResponse->assertJsonPath('openapi', '3.0.0');
        $specResponse->assertJsonPath('info.title', 'Freighteva Complete API Suite');
    }
}
