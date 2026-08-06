<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Review;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Tenant $tenant;
    protected Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // Retrieve or create a test user
        $this->user = User::firstOrCreate(
            ['email' => 'shipper@example.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => bcrypt('Password123'),
                'mobile' => '+1234567890'
            ]
        );

        // Retrieve or create a test tenant
        $this->tenant = Tenant::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Test Carrier LLC',
                'company_name' => 'Test Carrier LLC',
                'company_slug' => 'test-carrier-llc',
                'status' => 'active',
                'active' => 1
            ]
        );

        // Create a test shipment for this user and tenant
        $this->shipment = Shipment::create([
            'origin' => 'Toronto, ON',
            'destination' => 'Lagos, NG',
            'weight' => 10,
            'carrier' => 'DHL',
            'mode' => 'air',
            'status' => 'delivered',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'invoice_prefix' => 'INV',
            'invoice_no' => '123',
            'awb_number' => 'AWB123',
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => 2,
            'amount' => 100.00,
            'total_amount' => 100.00,
            'currency' => 'USD',
            'handling_fee' => 0.00
        ]);
    }

    /**
     * Test submitting a shipment review and updating tenant cache.
     */
    public function test_submit_shipment_review_success(): void
    {
        Sanctum::actingAs($this->user);

        $initialCount = \Illuminate\Support\Facades\DB::table('reviews')->where('tenant_id', $this->tenant->id)->count();

        $payload = [
            'rating' => 5,
            'comment' => 'Fast transit and safe handling.',
            'user_id' => $this->user->id
        ];

        $response = $this->postJson("/api/shipments/{$this->shipment->id}/reviews", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'shipment_id' => $this->shipment->id,
                    'rating' => 5,
                    'comment' => 'Fast transit and safe handling.',
                    'reviewer_name' => 'John Doe'
                ]
            ]);

        // Assert database has review record
        $this->assertDatabaseHas('reviews', [
            'shipment_id' => $this->shipment->id,
            'rating' => 5
        ]);

        // Assert tenant cache updated
        $this->tenant->refresh();
        $this->assertEquals($initialCount + 1, $this->tenant->rating_count);
    }

    /**
     * Test submitting duplicate reviews returns error.
     */
    public function test_submit_duplicate_review_fails(): void
    {
        Sanctum::actingAs($this->user);

        // First review
        Review::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shipment_id' => $this->shipment->id,
            'rating' => 4,
            'comment' => 'First review'
        ]);

        $payload = [
            'rating' => 5,
            'comment' => 'Second review attempt.'
        ];

        $response = $this->postJson("/api/shipments/{$this->shipment->id}/reviews", $payload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error_code' => 'SHIPMENT_ALREADY_REVIEWED'
            ]);
    }

    /**
     * Test fetching tenant reviews.
     */
    public function test_get_tenant_reviews_success(): void
    {
        // Create a review
        Review::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shipment_id' => $this->shipment->id,
            'rating' => 4,
            'comment' => 'Excellent job!'
        ]);

        $response = $this->getJson("/api/tenants/{$this->tenant->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'tenant_id',
                            'shipment_id',
                            'rating',
                            'comment',
                            'reviewer_name',
                            'created_at'
                        ]
                    ]
                ]
            ]);

        $this->assertNotEmpty($response->json('data.data'));
        $this->assertEquals('Excellent job!', $response->json('data.data.0.comment'));
    }
}
