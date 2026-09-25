<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Get real-time tracking timeline and milestone progress for any shipment.
     */
    public function track(Request $request, string $awb): JsonResponse
    {
        $cleanAwb = trim($awb);

        $shipment = Shipment::where('awb_number', $cleanAwb)
            ->orWhere('invoice_no', $cleanAwb)
            ->orWhereRaw("CONCAT(invoice_prefix, '-', invoice_no) = ?", [$cleanAwb])
            ->orWhere('id', is_numeric($cleanAwb) ? (int)$cleanAwb : 0)
            ->with(['trackingEvents' => function ($q) {
                $q->orderBy('event_time', 'asc')->orderBy('id', 'asc');
            }, 'tenant'])
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => "Shipment with tracking number '{$cleanAwb}' not found.",
            ], 404);
        }

        $progressPercentages = [
            'booked' => 15,
            'confirmed' => 15,
            'merchant_received' => 25,
            'accepted' => 35,
            'pickup_scheduled' => 50,
            'picked_up' => 65,
            'in_transit' => 80,
            'arrived' => 90,
            'delivered' => 100,
            'reassigned' => 30,
            'cancelled' => 0,
            'exception_unfulfillable' => 0,
        ];

        $currentStatus = strtolower($shipment->status);
        $progress = $progressPercentages[$currentStatus] ?? 30;

        $milestones = [
            [
                'step' => 1,
                'title' => 'Booked & Verified',
                'completed' => $progress >= 15,
                'current' => $progress === 15,
            ],
            [
                'step' => 2,
                'title' => 'Pickup & Processing',
                'completed' => $progress >= 65,
                'current' => $progress >= 25 && $progress <= 65,
            ],
            [
                'step' => 3,
                'title' => 'In Transit',
                'completed' => $progress >= 80,
                'current' => $progress === 80,
            ],
            [
                'step' => 4,
                'title' => 'Arrived at Hub',
                'completed' => $progress >= 90,
                'current' => $progress === 90,
            ],
            [
                'step' => 5,
                'title' => 'Delivered',
                'completed' => $progress === 100,
                'current' => $progress === 100,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $shipment->id,
                'tenant_id' => $shipment->tenant_id,
                'awb_number' => $shipment->awb_number,
                'invoice_number' => $shipment->invoice_prefix . '-' . $shipment->invoice_no,
                'status' => strtoupper($shipment->status),
                'progress_percentage' => $progress,
                'is_delivered' => $progress === 100,
                'origin' => $shipment->origin,
                'destination' => $shipment->destination,
                'weight_kg' => (float)$shipment->weight,
                'mode' => ucfirst($shipment->mode),
                'carrier' => $shipment->carrier ?: ($shipment->tenant?->name ?? 'Freighteva Partner'),
                'pickup_schedule' => [
                    'date' => $shipment->pickup_date,
                    'time_slot' => $shipment->pickup_time_slot,
                ],
                'milestones' => $milestones,
                'timeline' => $shipment->trackingEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'status' => $event->status,
                        'title' => $event->title,
                        'description' => $event->description,
                        'location' => $event->location,
                        'timestamp' => $event->event_time?->toIso8601String(),
                        'formatted_date' => $event->event_time?->format('M d, Y h:i A'),
                        'actor_type' => $event->actor_type,
                    ];
                }),
                'created_at' => $shipment->created_at?->toIso8601String(),
            ],
        ]);
    }
}
