<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\DiditKycService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiditKycController extends Controller
{
    protected DiditKycService $diditService;

    public function __construct(DiditKycService $diditService)
    {
        $this->diditService = $diditService;
    }

    /**
     * Check current customer KYC verification status by email.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if (empty($email)) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required to check verification status.',
            ], 422);
        }

        // 1. Check KycVerification table
        $kycRecord = KycVerification::where('email', $email)
            ->whereNotNull('verified_at')
            ->latest('id')
            ->first();

        // 2. Check User table
        $user = User::where('email', $email)->first();
        $isUserVerified = $user && (
            $user->kyc_verified_at !== null ||
            ($user->verification_status ?? 0) == 1 ||
            in_array(strtolower((string) ($user->kyc_status ?? '')), ['approved', 'verified', 'completed'])
        );

        $isVerified = ($kycRecord !== null) || $isUserVerified;
        $status = $isVerified ? 'verified' : ($kycRecord->status ?? 'unverified');

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $email,
                'is_verified' => (bool) $isVerified,
                'status' => $status,
                'verified_at' => $kycRecord?->verified_at?->toIso8601String() ?? $user?->kyc_verified_at?->toIso8601String(),
                'user_exists' => (bool) $user,
            ],
        ]);
    }

    /**
     * Create a hosted Didit verification session.
     */
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string',
            'callback_url' => 'nullable|url',
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $callbackUrl = $request->input('callback_url');

        try {
            $session = $this->diditService->createSession($email, $callbackUrl);
            $sessionId = $session['session_id'] ?? null;
            $verificationUrl = $session['url'] ?? null;

            if ($sessionId) {
                KycVerification::updateOrCreate(
                    ['session_id' => $sessionId],
                    [
                        'email' => $email,
                        'status' => $session['status'] ?? 'Not Started',
                        'decision_payload' => $session,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'session_token' => $session['session_token'] ?? null,
                    'verification_url' => $verificationUrl,
                    'status' => $session['status'] ?? 'Not Started',
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Didit KYC: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify session decision and update customer KYC status.
     */
    public function verifySession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'email' => 'required|email',
        ]);

        $sessionId = (string) $request->input('session_id');
        $email = strtolower(trim((string) $request->input('email')));

        $decision = $this->diditService->getSessionDecision($sessionId);
        $rawStatus = (string) ($decision['status'] ?? 'pending');
        $statusLower = strtolower($rawStatus);
        
        $isApproved = in_array($statusLower, ['approved', 'completed', 'verified']);

        // Update KycVerification record
        $kyc = KycVerification::firstOrNew(['session_id' => $sessionId]);
        $kyc->email = $email;
        $kyc->status = $rawStatus;
        $kyc->decision_payload = $decision;
        if ($isApproved && !$kyc->verified_at) {
            $kyc->verified_at = now();
        }
        $kyc->save();

        if ($isApproved) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->kyc_verified_at = now();
                $user->verification_status = 1;
                $user->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $sessionId,
                'email' => $email,
                'status' => $rawStatus,
                'is_verified' => $isApproved,
            ],
        ]);
    }

    /**
     * Sandbox / Testing helper: instantly approve KYC for an email (for development testing).
     */
    public function testApprove(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Email required.'], 422);
        }

        $session_id = 'sandbox_test_' . substr(md5($email . time()), 0, 12);

        KycVerification::create([
            'email' => $email,
            'session_id' => $session_id,
            'status' => 'Approved',
            'decision_payload' => ['test_mode' => true, 'status' => 'Approved'],
            'verified_at' => now(),
        ]);

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->kyc_verified_at = now();
            $user->verification_status = 1;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => "KYC successfully verified for {$email}",
            'data' => [
                'email' => $email,
                'is_verified' => true,
                'status' => 'verified',
            ],
        ]);
    }
}
