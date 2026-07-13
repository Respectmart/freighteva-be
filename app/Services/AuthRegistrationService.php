<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AuthRegistrationService
{
    /**
     * Register a new player user or merchant user.
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $nameParts = explode(' ', $data['name'], 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $tenantId = null;

            if ($data['role'] === 'merchant') {
                $tenant = Tenant::create([
                    'name' => $data['name'],
                    'company_name' => $data['name'],
                    'company_slug' => Str::slug($data['name']),
                    'status' => 'active',
                    'active' => 1,
                ]);
                $tenantId = $tenant->id;
            }

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenantId,
                'mobile' => $data['phone'] ?? '',
            ]);

            if ($data['role'] === 'merchant') {
                $user->assignRole('superadministrator');
            } else {
                $user->assignRole('user');
            }

            return $user;
        });
    }
}
