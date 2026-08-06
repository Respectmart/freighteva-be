<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Register a new user (merchant or shipper).
     */
    public function register(RegisterUserRequest $request, AuthRegistrationService $service): JsonResponse
    {
        $user = $service->register($request->validated());
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'access_token' => $token,
                'user_id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->first(),
                'tenant_id' => $user->tenant_id,
            ]
        ], 201);
    }

    /**
     * Authenticate user credentials and return a token.
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect email or password.',
                'error_code' => 'INVALID_CREDENTIALS',
                'errors' => new \stdClass()
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'access_token' => $token,
                'user' => new UserResource($user)
            ]
        ], 200);
    }

    /**
     * Fetch the currently authenticated user session.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user())
        ], 200);
    }
}
