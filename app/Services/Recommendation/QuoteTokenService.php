<?php

namespace App\Services\Recommendation;

use Illuminate\Support\Str;

class QuoteTokenService
{
    protected string $secretKey;

    public function __construct()
    {
        // Use application secret key for cryptographic HMAC signing
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        $this->secretKey = $key ?: 'FreightevaDefaultSecureQuoteSigningSecretKey2026';
    }

    /**
     * Generate an HMAC-SHA256 signed tamper-proof quote booking token.
     *
     * @param array $quoteData
     * @param int $ttlMinutes
     * @return array ['token' => string, 'expires_at' => int, 'quote_id' => string]
     */
    public function generateToken(array $quoteData, int $ttlMinutes = 15): array
    {
        $quoteId = (string) Str::uuid();
        $now = time();
        $expiresAt = $now + ($ttlMinutes * 60);

        $payload = array_merge($quoteData, [
            'quote_id' => $quoteId,
            'generated_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedPayload = $this->base64UrlEncode($jsonPayload);
        $signature = hash_hmac('sha256', $encodedPayload, $this->secretKey);

        $token = $encodedPayload . '.' . $signature;

        return [
            'token' => $token,
            'quote_id' => $quoteId,
            'expires_at' => $expiresAt,
            'expires_in_seconds' => $ttlMinutes * 60,
            'payload' => $payload,
        ];
    }

    /**
     * Verify token signature and check validity/expiration.
     *
     * @param string $token
     * @return array ['valid' => bool, 'reason' => string|null, 'payload' => array|null]
     */
    public function verifyToken(string $token): array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2) {
            return [
                'valid' => false,
                'reason' => 'MALFORMED_TOKEN_STRUCTURE',
                'payload' => null,
            ];
        }

        [$encodedPayload, $providedSignature] = $parts;

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->secretKey);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return [
                'valid' => false,
                'reason' => 'SIGNATURE_VERIFICATION_FAILED',
                'payload' => null,
            ];
        }

        $decodedJson = $this->base64UrlDecode($encodedPayload);
        if (!$decodedJson) {
            return [
                'valid' => false,
                'reason' => 'INVALID_PAYLOAD_ENCODING',
                'payload' => null,
            ];
        }

        $payload = json_decode($decodedJson, true);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return [
                'valid' => false,
                'reason' => 'INVALID_QUOTE_PAYLOAD',
                'payload' => null,
            ];
        }

        if (time() > (int)$payload['expires_at']) {
            return [
                'valid' => false,
                'reason' => 'QUOTE_EXPIRED',
                'payload' => $payload,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'payload' => $payload,
        ];
    }

    /**
     * Base64URL encode helper.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode helper.
     */
    protected function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
