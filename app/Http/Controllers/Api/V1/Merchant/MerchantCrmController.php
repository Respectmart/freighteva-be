<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Audit\MarketplaceAuditService;
use App\Services\Exception\ShipmentExceptionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantCrmController extends Controller
{
    protected ShipmentExceptionEngine $exceptionEngine;
    protected MarketplaceAuditService $auditService;

    public function __construct(
        ShipmentExceptionEngine $exceptionEngine,
        MarketplaceAuditService $auditService
    ) {
        $this->exceptionEngine = $exceptionEngine;
        $this->auditService = $auditService;
    }

    /**
     * Resolve target merchant from authentication or request context.
     */
    protected function resolveMerchant(Request $request): ?Tenant
    {
        $merchantId = $request->header('X-Merchant-ID')
            ?: ($request->input('merchant_id') ?: (auth()->user()?->tenant_id ?: 1));

        return Tenant::find($merchantId);
    }

    /**
     * List inbound marketplace bookings for the merchant CRM.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);

        if (!$merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant profile not found.',
            ], 404);
        }

        $query = Shipment::where('tenant_id', $merchant->id)->with('trackingEvents');

        // Status Filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = strtolower($request->input('status'));
            if ($status === 'new') {
                $query->whereIn('status', ['booked', 'confirmed', 'new', 'merchant_received']);
            } else {
                $query->where('status', $status);
            }
        }

        // Search Filter (AWB, origin, destination)
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('awb_number', 'like', "%{$s}%")
                  ->orWhere('invoice_no', 'like', "%{$s}%")
                  ->orWhere('origin', 'like', "%{$s}%")
                  ->orWhere('destination', 'like', "%{$s}%");
            });
        }

        // Mode Filter
        if ($request->filled('mode')) {
            $query->where('mode', 'like', '%' . $request->input('mode') . '%');
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'company_name' => $merchant->company_name ?? $merchant->name,
            ],
            'data' => $bookings->items(),
            'pagination' => [
                'total' => $bookings->total(),
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }

    /**
     * View detailed booking information for fulfillment.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);

        $shipment = Shipment::with(['trackingEvents', 'exceptions'])->find($id);

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($merchant && $shipment->tenant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This booking is not assigned to your merchant account.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $shipment->id,
                'awb_number' => $shipment->awb_number,
                'invoice_number' => $shipment->invoice_prefix . '-' . $shipment->invoice_no,
                'status' => $shipment->status,
                'payment_status' => $shipment->payment_status,
                'origin' => $shipment->origin,
                'destination' => $shipment->destination,
                'weight_kg' => (float)$shipment->weight,
                'mode' => $shipment->mode,
                'amount' => (float)$shipment->amount,
                'total_amount' => (float)$shipment->total_amount,
                'currency' => $shipment->currency,
                'insurance_value' => (float)$shipment->insurance_value,
                'pickup_schedule' => [
                    'date' => $shipment->pickup_date,
                    'time_slot' => $shipment->pickup_time_slot,
                    'type' => $shipment->pickup_type ?? 'Door-to-door',
                ],
                'tracking_timeline' => $shipment->trackingEvents,
                'exceptions' => $shipment->exceptions,
                'created_at' => $shipment->created_at?->toIso8601String(),
                'updated_at' => $shipment->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Acknowledge receipt of a booking in the Merchant CRM.
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);
        $shipment = Shipment::findOrFail($id);

        if ($merchant && $shipment->tenant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This booking belongs to a different merchant.',
            ], 403);
        }

        $shipment->update(['status' => 'merchant_received']);

        $shipment->recordTrackingEvent(
            status: 'MERCHANT_RECEIVED',
            title: 'Booking Received by Partner',
            description: "Fulfillment partner {$merchant->name} received booking details in their CRM.",
            location: $shipment->origin,
            actorType: 'merchant',
            actorId: $merchant->id
        );

        // Record Audit Log
        $this->auditService->logMerchantAction($merchant->id, 'acknowledge', $shipment);

        return response()->json([
            'success' => true,
            'message' => 'Booking acknowledged as received in Merchant CRM.',
            'status' => 'merchant_received',
        ]);
    }

    /**
     * Accept a booking for fulfillment.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);
        $shipment = Shipment::findOrFail($id);

        if ($merchant && $shipment->tenant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This booking belongs to a different merchant.',
            ], 403);
        }

        $shipment->update([
            'status' => 'accepted',
        ]);

        $shipment->recordTrackingEvent(
            status: 'ACCEPTED',
            title: 'Shipment Accepted by Partner',
            description: "Partner {$merchant->name} accepted fulfillment responsibility. Preparing pickup dispatch.",
            location: $shipment->origin,
            actorType: 'merchant',
            actorId: $merchant->id
        );

        // Record Audit Log
        $this->auditService->logMerchantAction($merchant->id, 'accept', $shipment);

        return response()->json([
            'success' => true,
            'message' => 'Shipment booking accepted for fulfillment.',
            'status' => 'accepted',
        ]);
    }

    /**
     * Update operational shipment lifecycle status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pickup_scheduled,picked_up,in_transit,arrived,delivered,cancelled',
            'location' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
        ]);

        $merchant = $this->resolveMerchant($request);
        $shipment = Shipment::findOrFail($id);

        if ($merchant && $shipment->tenant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This booking belongs to a different merchant.',
            ], 403);
        }

        $newStatus = $request->input('status');
        $location = $request->input('location', $shipment->origin);
        $customDescription = $request->input('description');

        $statusTitles = [
            'pickup_scheduled' => 'Pickup Scheduled',
            'picked_up' => 'Package Collected from Sender',
            'in_transit' => 'In Transit to Destination Hub',
            'arrived' => 'Arrived at Destination Port/Hub',
            'delivered' => 'Delivered to Final Recipient',
            'cancelled' => 'Shipment Cancelled',
        ];

        $statusDescriptions = [
            'pickup_scheduled' => "Pickup scheduled for {$shipment->pickup_date} ({$shipment->pickup_time_slot}).",
            'picked_up' => "Courier safely collected cargo at {$location}.",
            'in_transit' => "Cargo departed departure hub and is in transit.",
            'arrived' => "Cargo safely arrived at destination customs/clearance depot.",
            'delivered' => "Cargo successfully delivered to recipient back home. Confirmation recorded.",
            'cancelled' => "Shipment processing was cancelled.",
        ];

        $title = $statusTitles[$newStatus] ?? ucfirst(str_replace('_', ' ', $newStatus));
        $description = $customDescription ?: ($statusDescriptions[$newStatus] ?? "Status updated to {$newStatus}.");

        $shipment->update([
            'status' => $newStatus,
        ]);

        $event = $shipment->recordTrackingEvent(
            status: strtoupper($newStatus),
            title: $title,
            description: $description,
            location: $location,
            actorType: 'merchant',
            actorId: $merchant->id
        );

        // Record Audit Log
        $this->auditService->logMerchantAction($merchant->id, 'status', $shipment, [
            'new_status' => $newStatus,
            'location' => $location,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Shipment status successfully updated to {$newStatus}.",
            'data' => [
                'shipment_id' => $shipment->id,
                'awb_number' => $shipment->awb_number,
                'status' => $newStatus,
                'latest_event' => $event,
            ],
        ]);
    }

    /**
     * Reject a booking and trigger the automated Exception Engine.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason_code' => 'required|string|in:CAPACITY_FULL,SCHEDULE_CONFLICT,UNSUPPORTED_CARGO,PRICE_MISMATCH,OPERATIONAL_DELAY,OTHER',
            'reason_notes' => 'nullable|string|max:500',
        ]);

        $merchant = $this->resolveMerchant($request);
        $shipment = Shipment::findOrFail($id);

        if ($merchant && $shipment->tenant_id !== $merchant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This booking is not assigned to your merchant account.',
            ], 403);
        }

        // Record Audit Log
        $this->auditService->logMerchantAction($merchant->id, 'reject', $shipment, [
            'reason_code' => $request->input('reason_code'),
            'reason_notes' => $request->input('reason_notes'),
        ]);

        $result = $this->exceptionEngine->handleRejection(
            $shipment,
            $merchant,
            $request->input('reason_code'),
            $request->input('reason_notes')
        );

        return response()->json($result, $result['reassigned'] ? 200 : 422);
    }
}
