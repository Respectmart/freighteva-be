<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test booking a shipment creates database record for authenticated user.
     */
    public function test_book_shipment_success(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'shipper@example.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => bcrypt('Password123'),
                'mobile' => '+1234567890'
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'DHL Global',
                'company_name' => 'DHL Global',
                'company_slug' => 'dhl-global',
                'status' => 'active',
                'active' => 1
            ]
        );

        Sanctum::actingAs($user);

        $payload = [
            'tenant_id' => $tenant->id,
            'origin' => 'Toronto, ON',
            'destination' => 'Lagos, NG',
            'weight' => 12.5,
            'mode' => 'Air Freight',
            'carrier' => 'DHL Global',
            'price' => 150.00
        ];

        $response = $this->postJson('/api/shipments', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment booked successfully!',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'origin' => 'Toronto, ON',
                    'destination' => 'Lagos, NG',
                    'weight' => 12.5,
                    'mode' => 'Air Freight',
                    'carrier' => 'DHL Global',
                    'status' => 'booked',
                    'user_id' => $user->id
                ]
            ]);

        $this->assertDatabaseHas('shipments', [
            'tenant_id' => $tenant->id,
            'origin' => 'Toronto, ON',
            'status' => 'booked',
            'user_id' => $user->id
        ]);
    }
}
