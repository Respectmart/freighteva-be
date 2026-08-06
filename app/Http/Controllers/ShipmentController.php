<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shipment\StoreShipmentRequest;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;

class ShipmentController extends Controller
{
    /**
     * List all shipments for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $shipments = Shipment::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments
        ]);
    }

    /**
     * Book a new shipment for the authenticated user.
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = Shipment::create([
            'tenant_id' => $request->input('tenant_id'),
            'origin' => $request->input('origin'),
            'destination' => $request->input('destination'),
            'weight' => $request->input('weight'),
            'mode' => $request->input('mode'),
            'carrier' => $request->input('carrier'),
            'status' => 'booked',
            'invoice_prefix' => 'INV',
            'invoice_no' => (string)mt_rand(100000, 999999),
            'awb_number' => 'AWB' . mt_rand(100000, 999999),
            'shipment_receiver_id' => 1,
            'shipment_sender_id' => 1,
            'freight_id' => str_contains(strtolower($request->input('mode')), 'ocean') ? 1 : 2,
            'amount' => $request->input('price'),
            'total_amount' => $request->input('price'),
            'currency' => 'USD',
            'handling_fee' => 0.00,
            'user_id' => auth()->id(), // Authenticated user
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment booked successfully!',
            'data' => $shipment
        ], 201);
    }

    /**
     * Retrieve public details of a shipment for guest reviews.
     */
    public function showPublic(\App\Models\Shipment $shipment): JsonResponse
    {
        $carrierName = $shipment->carrier;
        if (empty($carrierName) && $shipment->tenant_id) {
            $tenant = \App\Models\Tenant::find($shipment->tenant_id);
            $carrierName = $tenant ? $tenant->company_name : 'Verified Carrier';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $shipment->id,
                'invoice_no' => $shipment->invoice_prefix . '-' . $shipment->invoice_no,
                'awb_number' => $shipment->awb_number,
                'origin' => $shipment->origin,
                'destination' => $shipment->destination,
                'weight' => $shipment->weight,
                'carrier' => $carrierName,
                'tenant_id' => $shipment->tenant_id,
                'status' => $shipment->status,
                'payment_status' => $shipment->payment_status,
            ]
        ]);
    }
}
