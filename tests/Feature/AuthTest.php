<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test standard shipper registration.
     */
    public function test_shipper_registration_success(): void
    {
        $payload = [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'shipper'
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'user_id',
                    'name',
                    'email',
                    'role',
                    'tenant_id'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ali@example.com',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'tenant_id' => null
        ]);

        $user = User::where('email', 'ali@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
    }

    /**
     * Test merchant registration and tenant auto-creation.
     */
    public function test_merchant_registration_creates_tenant(): void
    {
        $payload = [
            'name' => 'Dipson Logistics Ltd',
            'email' => 'dipson@logistics.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'role' => 'merchant'
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'access_token',
                    'tenant_id'
                ]
            ]);

        $tenantId = $response->json('data.tenant_id');
        $this->assertNotNull($tenantId);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'company_name' => 'Dipson Logistics Ltd',
            'company_slug' => 'dipson-logistics-ltd',
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'dipson@logistics.com',
            'tenant_id' => $tenantId
        ]);

        $user = User::where('email', 'dipson@logistics.com')->first();
        $this->assertTrue($user->hasRole('superadministrator'));
    }

    /**
     * Test user login.
     */
    public function test_login_success(): void
    {
        $user = User::create([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'password' => bcrypt('Password@123'),
            'mobile' => '+923001234567'
        ]);
        $user->assignRole('user');

        $payload = [
            'email' => 'ali@example.com',
            'password' => 'Password@123'
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'tenant_id'
                    ]
                ]
            ]);
    }

    /**
     * Test login failure with bad credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword'
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Incorrect email or password.',
                'error_code' => 'INVALID_CREDENTIALS'
            ]);
    }

    /**
     * Test fetching session profile context.
     */
    public function test_get_session_profile_success(): void
    {
        $user = User::create([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'password' => bcrypt('Password@123'),
            'mobile' => '+923001234567'
        ]);
        $user->assignRole('user');

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Ali Khan',
                    'email' => 'ali@example.com',
                    'role' => 'user',
                    'tenant_id' => null
                ]
            ]);
    }
}
