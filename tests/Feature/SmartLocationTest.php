<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmartLocationTest extends TestCase
{
    /**
     * Test smart location geocoding with city query.
     */
    public function test_smart_location_city_query(): void
    {
        $response = $this->getJson('/api/search/smart-location?q=Marysville&country=US&lat=48.0518&lon=-122.1771');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /**
     * Test smart location with postal code.
     */
    public function test_smart_location_postal_code(): void
    {
        $response = $this->getJson('/api/search/smart-location?q=98270&country=US');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /**
     * Test rates search with raw city name.
     */
    public function test_rates_with_raw_city(): void
    {
        $response = $this->postJson('/api/search/rates', [
            'from' => 'Marysville',
            'to' => 'Lagos',
            'mode' => 'Air Freight',
            'weight' => 5,
            'unit' => 'kg'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test searching for a country like United Kingdom prioritizes UK (GB).
     */
    public function test_smart_location_country_search_prioritizes_exact_country(): void
    {
        $response = $this->getJson('/api/search/smart-location?q=United%20Kingdom&type=to&country=US');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('GB', $data[0]['iso_code']);
        $this->assertStringContainsString('United Kingdom', $data[0]['country']);
    }

    /**
     * Test rates search from Vancouver, Washington, United States to Manchester, England, United Kingdom.
     */
    public function test_rates_search_vancouver_wa_to_manchester_uk(): void
    {
        $response = $this->postJson('/api/search/rates', [
            'from' => 'Vancouver, Washington, United States',
            'to' => 'Manchester, England, United Kingdom',
            'mode' => 'Air Freight',
            'weight' => 5,
            'unit' => 'kg'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}


