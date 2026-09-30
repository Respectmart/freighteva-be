<?php

namespace App\Services\Exception;

use App\Models\MerchantBookingException;
use App\Models\MerchantPerformanceLog;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Audit\MarketplaceAuditService;
use App\Services\Recommendation\PartnerRecommendationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentExceptionEngine
{
    protected PartnerRecommendationEngine $recommendationEngine;
    protected MarketplaceAuditService $auditService;

    public function __construct(
        PartnerRecommendationEngine $recommendationEngine,
        MarketplaceAuditService $auditService
    ) {
        $this->recommendationEngine = $recommendationEngine;
        $this->auditService = $auditService;
    }

    /**
     * Handle merchant rejection of a booking.
     * Records exception, penalizes merchant, discovers alternative endorsed partner, and auto-reassigns.
     *
     * @param Shipment $shipment
     * @param Tenant $rejectingMerchant
     * @param string $reasonCode (e.g. CAPACITY_FULL, SCHEDULE_CONFLICT, UNSUPPORTED_CARGO, PRICE_MISMATCH, OPERATIONAL_DELAY, OTHER)
     * @param string|null $reasonNotes
     * @return array
     */
    public function handleRejection(
        Shipment $shipment,
        Tenant $rejectingMerchant,
        string $reasonCode,
        ?string $reasonNotes = null
    ): array {
        return DB::transaction(function () use ($shipment, $rejectingMerchant, $reasonCode, $reasonNotes) {
            // 1. Log Rejection Penalty for the Merchant
            $penaltyPoints = 5.00;
            MerchantPerformanceLog::create([
                'tenant_id' => $rejectingMerchant->id,
                'metric_type' => 'rejection_penalty',
                'impact_score' => -$penaltyPoints,
                'notes' => "Rejected Booking #{$shipment->id} ({$shipment->awb_number}) due to: {$reasonCode}. " . ($reasonNotes ?: ''),
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'reason_code' => $reasonCode,
                    'reason_notes' => $reasonNotes,
                    'awb_number' => $shipment->awb_number,
                ],
            ]);

            // Adjust merchant rating score / booking success rate if present
            if ($rejectingMerchant->rating_score && $rejectingMerchant->rating_score > 1.0) {
                $rejectingMerchant->decrement('rating_score', 0.05);
            }

            // 2. Discover Alternative Endorsed Partners for this Route
            $originCountryId = null;
            $destCountryId = null;

            // Attempt to resolve origin/dest country IDs from existing routes or country table
            $originMatch = DB::table('countries')
                ->where('name', 'like', '%' . substr($shipment->origin, 0, 15) . '%')
                ->orWhere('capital', 'like', '%' . substr($shipment->origin, 0, 15) . '%')
                ->first();
            $destMatch = DB::table('countries')
                ->where('name', 'like', '%' . substr($shipment->destination, 0, 15) . '%')
                ->orWhere('capital', 'like', '%' . substr($shipment->destination, 0, 15) . '%')
                ->first();

            $searchParams = [
                'origin_country_id' => $originMatch?->id ?? 1,
                'destination_country_id' => $destMatch?->id ?? 2,
                'mode' => $shipment->freight_mode ?: ($shipment->mode ?: 'air'),
                'weight_kg' => (float)($shipment->weight ?: 1.0),
            ];

            $recResult = $this->recommendationEngine->getRecommendations($searchParams);
            $recommendations = $recResult['recommendations'] ?? [];

            // Filter out rejecting merchant and any already rejected merchants for this shipment
            $alreadyRejectedMerchantIds = MerchantBookingException::where('shipment_id', $shipment->id)
                ->pluck('original_tenant_id')
                ->push($rejectingMerchant->id)
                ->unique()
                ->toArray();

            $availableAlternatives = array_values(array_filter($recommendations, function ($rec) use ($alreadyRejectedMerchantIds) {
                $merchantId = $rec['partner_attribution']['merchant_id'] ?? null;
                return $merchantId && !in_array($merchantId, $alreadyRejectedMerchantIds);
            }));

            // 3. Reassign to Alternative Partner if available
            if (!empty($availableAlternatives)) {
                $selectedAlt = $availableAlternatives[0];
                $newPartner = Tenant::find($selectedAlt['partner_attribution']['merchant_id']);

                $reassignedTenantId = $newPartner?->id;
                $newCarrierName = $newPartner?->company_name ?? ($newPartner?->name ?? 'Freighteva Partner');

                // Update Shipment Record
                $shipment->update([
                    'tenant_id' => $reassignedTenantId,
                    'carrier' => $newCarrierName,
                    'status' => 'reassigned',
                ]);

                // Create Exception Record
                $exception = MerchantBookingException::create([
                    'shipment_id' => $shipment->id,
                    'original_tenant_id' => $rejectingMerchant->id,
                    'reassigned_tenant_id' => $reassignedTenantId,
                    'reason_code' => $reasonCode,
                    'reason_notes' => $reasonNotes,
                    'penalty_applied' => true,
                    'penalty_points' => $penaltyPoints,
                    'resolution_status' => 'REASSIGNED',
                    'resolved_at' => now(),
                    'metadata' => [
                        'alternative_service' => $selectedAlt['service_name'] ?? 'Freighteva Partner',
                        'reassigned_price' => $selectedAlt['calculated_price'] ?? $shipment->amount,
                    ],
                ]);

                // Log Tracking Timeline Event
                $shipment->recordTrackingEvent(
                    status: 'EXCEPTION_REASSIGNED',
                    title: 'Fulfillment Partner Reassigned',
                    description: "Fulfillment automatically reassigned to verified partner '{$newCarrierName}' for seamless priority fulfillment.",
                    location: $shipment->origin,
                    actorType: 'system',
                    metadata: [
                        'previous_merchant' => $rejectingMerchant->company_name ?? $rejectingMerchant->name,
                        'new_merchant' => $newCarrierName,
                        'reason_code' => $reasonCode,
                    ]
                );

                // Record Audit Log
                $this->auditService->logExceptionReassigned(
                    shipment: $shipment,
                    originalTenantId: $rejectingMerchant->id,
                    reassignedTenantId: $reassignedTenantId,
                    reasonCode: $reasonCode,
                    metadata: [
                        'reassigned_carrier' => $newCarrierName,
                        'service_name' => $selectedAlt['service_name'] ?? 'Freighteva Partner',
                    ]
                );

                Log::info("Shipment #{$shipment->id} ({$shipment->awb_number}) successfully reassigned from Merchant #{$rejectingMerchant->id} to Merchant #{$reassignedTenantId}.");

                return [
                    'success' => true,
                    'reassigned' => true,
                    'message' => "Booking rejected by {$rejectingMerchant->name}. Exception engine automatically reassigned shipment to {$newCarrierName}.",
                    'exception_id' => $exception->id,
                    'reassigned_to' => [
                        'merchant_id' => $reassignedTenantId,
                        'company_name' => $newCarrierName,
                        'service_name' => $selectedAlt['service_name'] ?? 'Freighteva Partner',
                    ],
                    'shipment_status' => 'reassigned',
                ];
            }

            // 4. Fallback when NO Alternative Partner is Available
            $shipment->update([
                'status' => 'exception_unfulfillable',
            ]);

            $exception = MerchantBookingException::create([
                'shipment_id' => $shipment->id,
                'original_tenant_id' => $rejectingMerchant->id,
                'reassigned_tenant_id' => null,
                'reason_code' => $reasonCode,
                'reason_notes' => $reasonNotes,
                'penalty_applied' => true,
                'penalty_points' => $penaltyPoints,
                'resolution_status' => 'REFUND_PENDING',
                'resolved_at' => null,
                'metadata' => [
                    'notice' => 'No alternative endorsed partner available on this route.',
                ],
            ]);

            $shipment->recordTrackingEvent(
                status: 'EXCEPTION_UNFULFILLABLE',
                title: 'Fulfillment Exception - Pending Refund',
                description: "The assigned partner could not fulfill this route, and no secondary partner was available. A full refund has been initiated.",
                location: $shipment->origin,
                actorType: 'system',
                metadata: [
                    'reason_code' => $reasonCode,
                ]
            );

            // Record Audit Log
            $this->auditService->logExceptionReassigned(
                shipment: $shipment,
                originalTenantId: $rejectingMerchant->id,
                reassignedTenantId: null,
                reasonCode: $reasonCode,
                metadata: [
                    'outcome' => 'REFUND_PENDING',
                ]
            );

            Log::warning("Shipment #{$shipment->id} rejected by Merchant #{$rejectingMerchant->id} with no alternative partner available.");

            return [
                'success' => true,
                'reassigned' => false,
                'refund_initiated' => true,
                'message' => "Booking rejected. No alternative partner available on this corridor. Automatic refund initiated.",
                'exception_id' => $exception->id,
                'shipment_status' => 'exception_unfulfillable',
            ];
        });
    }
}
