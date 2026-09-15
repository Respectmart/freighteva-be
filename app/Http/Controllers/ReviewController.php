<?php

namespace App\Http\Controllers;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * Submit a review for a shipment.
     */
    public function store(StoreReviewRequest $request, Shipment $shipment): JsonResponse
    {
        // Enforce that the user submitting the review is the owner of the shipment
        if ($shipment->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review shipments that you booked.',
                'error_code' => 'UNAUTHORIZED_SHIPMENT_REVIEW',
                'errors' => new \stdClass()
            ], 403);
        }

        // Check if shipment has already been reviewed
        $existingReview = Review::where('shipment_id', $shipment->id)->first();
        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment has already been reviewed.',
                'error_code' => 'SHIPMENT_ALREADY_REVIEWED',
                'errors' => new \stdClass()
            ], 400);
        }

        // Validate that tenant is associated with the shipment
        $tenantId = $shipment->tenant_id;
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment is not associated with any tenant/carrier.',
                'error_code' => 'NO_TENANT_ASSOCIATED',
                'errors' => new \stdClass()
            ], 400);
        }

        $review = DB::transaction(function () use ($request, $shipment, $tenantId) {
            // Create the review — rating_avg/count are computed on-the-fly in search queries
            return Review::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'shipment_id' => $shipment->id,
                'rating' => $request->input('rating'),
                'comment' => $request->input('comment'),
            ]);
        });

        // Load relations
        $review->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => new ReviewResource($review)
        ], 201);
    }

    /**
     * Retrieve public reviews for a tenant.
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $reviews = Review::where('tenant_id', $tenant->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews)->response()->getData(true)
        ], 200);
    }

    /**
     * Submit a public guest review for a shipment using a shared link.
     */
    public function storePublic(\Illuminate\Http\Request $request, Shipment $shipment): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $existingReview = Review::where('shipment_id', $shipment->id)->first();
        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment has already been reviewed.',
                'error_code' => 'SHIPMENT_ALREADY_REVIEWED'
            ], 400);
        }

        $tenantId = $shipment->tenant_id;
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment is not associated with any tenant/carrier.',
                'error_code' => 'NO_TENANT_ASSOCIATED'
            ], 400);
        }

        $review = DB::transaction(function () use ($request, $shipment, $tenantId) {
            // Create the review — rating_avg/count are computed on-the-fly in search queries
            return Review::create([
                'tenant_id' => $tenantId,
                'user_id' => $shipment->user_id ?? 1,
                'shipment_id' => $shipment->id,
                'rating' => $request->input('rating'),
                'comment' => $request->input('comment'),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => new ReviewResource($review)
        ], 201);
    }
}
