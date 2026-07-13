<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test autocomplete location suggestions.
     */
    public function test_autocomplete_location_suggestions_success(): void
    {
        $response = $this->getJson('/api/search/autocomplete?q=Tor');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'city',
                        'country',
                        'iso_code'
                    ]
                ]
            ]);

        $this->assertNotEmpty($response->json('data'));
        $countries = collect($response->json('data'))->pluck('country')->toArray();
        $this->assertContains('Canada', $countries);
    }

    /**
     * Test search rates returns seeded local ranked providers.
     */
    public function test_search_rates_returns_ranked_providers_success(): void
    {
        $payload = [
            'from' => 'Toronto, ON',
            'to' => 'Lagos, NG',
            'mode' => 'Air Freight',
            'weight' => 5,
            'unit' => 'kg'
        ];

        $response = $this->postJson('/api/search/rates', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'initial',
                        'name',
                        'verified',
                        'rating',
                        'reviews',
                        'location',
                        'pickup',
                        'tags',
                        'days',
                        'price',
                        'total',
                        'featured'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Verify ranking order (advanced subscription tier 'Dipson Logistics LLC' first)
        $this->assertEquals('Dipson Logistics LLC', $data[0]['name']);
        $this->assertTrue($data[0]['featured']);
    }

    /**
     * Test search rates using explicit sending_country_id and receiving_country_id.
     */
    public function test_search_rates_with_country_ids_success(): void
    {
        $payload = [
            'from' => 'Ignored Name',
            'from_id' => 39,
            'to' => 'Ignored Name',
            'to_id' => 161,
            'mode' => 'Air Freight',
            'weight' => 5,
            'unit' => 'kg'
        ];

        $response = $this->postJson('/api/search/rates', $payload);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Dipson Logistics LLC', $data[0]['name']);
    }

    /**
     * Test search rates returns simulated premium couriers for Direct Carrier mode.
     */
    public function test_search_rates_returns_direct_couriers_success(): void
    {
        $payload = [
            'from' => 'Toronto, ON',
            'to' => 'Lagos, NG',
            'mode' => 'Direct Carrier',
            'weight' => 10,
            'unit' => 'kg'
        ];

        $response = $this->postJson('/api/search/rates', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'name',
                        'rating',
                        'price',
                        'total'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test popular routes endpoint returns active routes with explicit database country IDs.
     */
    public function test_popular_routes_endpoint_returns_ids_success(): void
    {
        $response = $this->getJson('/api/public/popular-routes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'from',
                        'from_id',
                        'to',
                        'to_id',
                        'fromFlag',
                        'toFlag'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertNotNull($data[0]['from_id']);
        $this->assertNotNull($data[0]['to_id']);
    }
}
