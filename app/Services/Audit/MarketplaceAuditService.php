<?php

namespace App\Services\Audit;

use App\Models\MarketplaceAuditLog;
use App\Models\Shipment;
use Illuminate\Support\Facades\Request;

class MarketplaceAuditService
{
    /**
     * Standardized Event Types for Audit Trail
     */
    public const EVENT_QUOTE_GENERATED = 'quote_generated';
    public const EVENT_QUOTE_VERIFIED = 'quote_verified';
    public const EVENT_BOOKING_CREATED = 'booking_created';
    public const EVENT_MERCHANT_ACKNOWLEDGED = 'merchant_acknowledged';
    public const EVENT_MERCHANT_ACCEPTED = 'merchant_accepted';
    public const EVENT_MERCHANT_REJECTED = 'merchant_rejected';
    public const EVENT_STATUS_UPDATED = 'status_updated';
    public const EVENT_EXCEPTION_REASSIGNED = 'exception_reassigned';
    public const EVENT_EXCEPTION_UNFULFILLABLE = 'exception_unfulfillable';
    public const EVENT_ENDORSEMENT_UPDATED = 'endorsement_updated';

    /**
     * Record a generalized audit log entry.
     */
    public function record(
        string $eventType,
        string $entityType,
        ?int $entityId = null,
        array $payload = [],
        ?int $tenantId = null,
        string $actorType = 'system',
        ?int $actorId = null
    ): MarketplaceAuditLog {
        return MarketplaceAuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => Request::ip() ?: '127.0.0.1',
            'user_agent' => Request::userAgent() ?: 'Internal System Engine',
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a quote generation event.
     */
    public function logQuoteGenerated(array $searchParams, int $totalQuotes, int $eligibleCount, array $diagnostics = []): MarketplaceAuditLog
    {
        return $this->record(
            eventType: self::EVENT_QUOTE_GENERATED,
            entityType: 'quote',
            entityId: null,
            payload: [
                'search_params' => $searchParams,
                'total_quotes' => $totalQuotes,
                'eligible_count' => $eligibleCount,
                'diagnostics' => $diagnostics,
            ],
            tenantId: null,
            actorType: 'customer'
        );
    }

    /**
     * Log a unified booking creation event.
     */
    public function logBookingCreated(Shipment $shipment, array $quoteDetails = []): MarketplaceAuditLog
    {
        return $this->record(
            eventType: self::EVENT_BOOKING_CREATED,
            entityType: 'shipment',
            entityId: $shipment->id,
            payload: [
                'awb_number' => $shipment->awb_number,
                'origin' => $shipment->origin,
                'destination' => $shipment->destination,
                'mode' => $shipment->mode,
                'weight' => $shipment->weight,
                'total_amount' => $shipment->total_amount,
                'currency' => $shipment->currency,
                'quote_details' => $quoteDetails,
            ],
            tenantId: $shipment->tenant_id,
            actorType: 'customer',
            actorId: $shipment->user_id
        );
    }

    /**
     * Log a merchant CRM action (acknowledge, accept, status update, reject).
     */
    public function logMerchantAction(int $tenantId, string $action, Shipment $shipment, array $metadata = []): MarketplaceAuditLog
    {
        $eventTypeMap = [
            'acknowledge' => self::EVENT_MERCHANT_ACKNOWLEDGED,
            'accept' => self::EVENT_MERCHANT_ACCEPTED,
            'reject' => self::EVENT_MERCHANT_REJECTED,
            'status' => self::EVENT_STATUS_UPDATED,
        ];

        $eventType = $eventTypeMap[$action] ?? self::EVENT_STATUS_UPDATED;

        return $this->record(
            eventType: $eventType,
            entityType: 'shipment',
            entityId: $shipment->id,
            payload: array_merge([
                'action' => $action,
                'awb_number' => $shipment->awb_number,
                'current_status' => $shipment->status,
            ], $metadata),
            tenantId: $tenantId,
            actorType: 'merchant',
            actorId: $tenantId
        );
    }

    /**
     * Log an exception reassignment event.
     */
    public function logExceptionReassigned(
        Shipment $shipment,
        int $originalTenantId,
        ?int $reassignedTenantId,
        string $reasonCode,
        array $metadata = []
    ): MarketplaceAuditLog {
        return $this->record(
            eventType: $reassignedTenantId ? self::EVENT_EXCEPTION_REASSIGNED : self::EVENT_EXCEPTION_UNFULFILLABLE,
            entityType: 'exception',
            entityId: $shipment->id,
            payload: array_merge([
                'shipment_id' => $shipment->id,
                'awb_number' => $shipment->awb_number,
                'original_tenant_id' => $originalTenantId,
                'reassigned_tenant_id' => $reassignedTenantId,
                'reason_code' => $reasonCode,
            ], $metadata),
            tenantId: $originalTenantId,
            actorType: 'system'
        );
    }
}
