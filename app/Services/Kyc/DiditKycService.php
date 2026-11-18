<?php

namespace App\Services\Kyc;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiditKycService
{
    protected string $apiKey;
    protected string $workflowId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.didit.api_key');
        $this->workflowId = (string) config('services.didit.workflow_id');
        $this->baseUrl = rtrim((string) config('services.didit.base_url', 'https://verification.didit.me/v3'), '/');
    }

    /**
     * Create a new hosted verification session on Didit.
     *
     * @param string $vendorData Internal reference (e.g. email, user_id, or booking_token)
     * @param string|null $callbackUrl User redirect URL after verification
     * @return array
     * @throws Exception
     */
    public function createSession(string $vendorData, ?string $callbackUrl = null): array
    {
        if (empty($this->apiKey) || empty($this->workflowId)) {
            throw new Exception('Didit KYC is not properly configured. Missing API Key or Workflow ID.');
        }

        $payload = [
            'workflow_id' => $this->workflowId,
            'vendor_data' => $vendorData,
        ];

        if ($callbackUrl) {
            $payload['callback'] = $callbackUrl;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/session/", $payload);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Didit KYC Session Creation Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new Exception('Failed to create Didit verification session: ' . ($response->json('message') ?? $response->body()));
    }

    /**
     * Query the verification session decision / status by session_id.
     *
     * @param string $sessionId
     * @return array
     */
    public function getSessionDecision(string $sessionId): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->baseUrl}/session/{$sessionId}/decision/");

        if ($response->successful()) {
            return $response->json();
        }

        return [
            'status' => 'Unknown',
            'error' => $response->body(),
        ];
    }
}
