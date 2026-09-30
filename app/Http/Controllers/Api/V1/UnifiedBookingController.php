<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Services\Audit\MarketplaceAuditService;
use App\Services\Recommendation\QuoteTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnifiedBookingController extends Controller
{
    protected QuoteTokenService $tokenService;
    protected MarketplaceAuditService $auditService;

    public function __construct(
        QuoteTokenService $tokenService,
        MarketplaceAuditService $auditService
    ) {
        $this->tokenService = $tokenService;
        $this->auditService = $auditService;
    }

    /**
     * Verify quote rate lock and booking token validity before checkout (Step 1).
     */
    public function verifyQuote(Request $request): JsonResponse
    {
        $request->validate([
            'booking_token' => 'required|string',
        ]);

        $verification = $this->tokenService->verifyToken($request->input('booking_token'));

        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'reason' => $verification['reason'],
                'message' => $this->getVerificationErrorMessage($verification['reason']),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'quote' => $verification['payload'],
        ]);
    }

    /**
     * Create an In-Platform Unified Booking (Steps 2-6 Complete).
     */
    public function createBooking(Request $request): JsonResponse
    {
        $request->validate([
            'booking_token' => 'required|string',
            'sender.name' => 'required|string|max:120',
            'sender.email' => 'required|email|max:120',
            'sender.phone' => 'required|string|max:30',
            'sender.address' => 'required|string|max:255',
            'sender.city' => 'nullable|string|max:100',
            'sender.postal_code' => 'nullable|string|max:20',
            'sender.pickup_date' => 'nullable|date',
            'sender.pickup_time_slot' => 'nullable|string|max:50',
            'receiver.name' => 'required|string|max:120',
            'receiver.email' => 'nullable|email|max:120',
            'receiver.phone' => 'required|string|max:30',
            'receiver.address' => 'required|string|max:255',
            'receiver.city' => 'nullable|string|max:100',
            'receiver.postal_code' => 'nullable|string|max:20',
            'receiver.country' => 'nullable|string|max:100',
            'cargo.item_description' => 'nullable|string|max:500',
            'cargo.declared_value' => 'nullable|numeric|min:0',
            'cargo.package_type' => 'nullable|string|max:50',
            'protection_plan' => 'nullable|string|in:basic,premium',
            'payment.payment_method' => 'nullable|string|in:card,wallet,demo_checkout,stripe',
            'payment.payment_intent_id' => 'nullable|string',
            'payment_intent_id' => 'nullable|string',
        ]);

        // 1. Verify HMAC Rate Lock Token
        $verification = $this->tokenService->verifyToken($request->input('booking_token'));

        if (!$verification['valid']) {
            return response()->json([
                'success' => false,
                'message' => $this->getVerificationErrorMessage($verification['reason']),
                'reason' => $verification['reason'],
            ], 422);
        }

        $quote = $verification['payload'];

        // 2. Compute Total Price with Protection Plan
        $basePrice = (float)($quote['calculated_price'] ?? 0);
        $protectionPlan = $request->input('protection_plan', 'basic');
        $protectionFee = ($protectionPlan === 'premium') ? 15.00 : 0.00;
        $totalAmount = round($basePrice + $protectionFee, 2);
        $currency = $quote['currency'] ?? 'USD';

        // 3. Verify Stripe PaymentIntent if provided
        $paymentIntentId = $request->input('payment.payment_intent_id') ?? $request->input('payment_intent_id');
        $stripeSecret = config('services.stripe.secret');

        if (!empty($stripeSecret) && !empty($paymentIntentId)) {
            try {
                \Stripe\Stripe::setApiKey($stripeSecret);
                $stripeIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
                if ($stripeIntent->status !== 'succeeded') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stripe payment has not been successfully completed. PaymentIntent status: ' . $stripeIntent->status,
                    ], 422);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Stripe PaymentIntent verification warning: ' . $e->getMessage());
            }
        }

        // 4. Generate Consistent Sequential Tracking AWB & Invoice Numbers
        $nextId = (\App\Models\Shipment::max('id') ?? 0) + 1;
        $formattedSeq = sprintf('%06d', $nextId);

        $invoicePrefix = 'EVA';
        $invoiceNo = $formattedSeq;
        $awbNumber = 'EVA-AWB-' . $formattedSeq;

        $sender = $request->input('sender', []);
        $receiver = $request->input('receiver', []);
        $cargo = $request->input('cargo', []);

        $originString = trim(($sender['address'] ?? '') . ', ' . ($sender['city'] ?? '') . ' ' . ($sender['postal_code'] ?? ''));
        if (empty(trim($originString, ', '))) {
            $originString = 'Origin Depot';
        }

        $destString = trim(($receiver['address'] ?? '') . ', ' . ($receiver['city'] ?? '') . ' ' . ($receiver['country'] ?? ''));
        if (empty(trim($destString, ', '))) {
            $destString = 'Destination Depot';
        }

        $carrierName = $quote['tenant_company'] ?? ($quote['service_name'] ?? 'Freighteva Partner');

        // Find or create customer user scoped to the specific merchant tenant
        $senderEmail = $sender['email'] ?? null;
        $customerUser = null;
        $targetTenantId = $quote['tenant_id'] ?? 1;

        if (!empty($senderEmail)) {
            $customerUser = \App\Models\User::where('email', $senderEmail)
                ->where('tenant_id', $targetTenantId)
                ->first();

            if (!$customerUser) {
                $nameParts = explode(' ', trim($sender['name'] ?? 'Freight Customer'), 2);
                $customerUser = \App\Models\User::create([
                    'first_name' => $nameParts[0] ?? 'Freight',
                    'last_name' => $nameParts[1] ?? 'Customer',
                    'email' => $senderEmail,
                    'mobile' => $sender['phone'] ?? 'N/A',
                    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                    'tenant_id' => $targetTenantId,
                ]);

                try {
                    $customerUser->assignRole('user');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => 3,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $customerUser->id,
                    ]);
                }
            }
        }

        // Create ShipmentSender record
        $shipmentSender = \App\Models\ShipmentSender::create([
            'name' => $sender['name'] ?? 'Freight Customer',
            'company_name' => $sender['company'] ?? null,
            'email' => $senderEmail ?? 'customer@freighteva.com',
            'phone_no' => !empty($sender['phone']) ? $sender['phone'] : '+10000000000',
            'address' => $sender['address'] ?? 'Origin Address',
            'city' => !empty($sender['city']) ? $sender['city'] : 'Origin City',
            'postal_code' => !empty($sender['postal_code']) ? substr($sender['postal_code'], 0, 10) : '00000',
            'country_id' => $quote['origin_country_id'] ?? 233,
            'zone_id' => 1,
        ]);

        // Create ShipmentReceiver record (matching shipment_receivers schema: first_name, last_name, email, mobile, zip_code)
        $receiverFullName = trim($receiver['name'] ?? 'Recipient Customer');
        $receiverNameParts = explode(' ', $receiverFullName, 2);
        $receiverFirstName = !empty($receiverNameParts[0]) ? $receiverNameParts[0] : 'Recipient';
        $receiverLastName = !empty($receiverNameParts[1]) ? $receiverNameParts[1] : 'Customer';

        $shipmentReceiver = \App\Models\ShipmentReceiver::create([
            'first_name' => substr($receiverFirstName, 0, 50),
            'last_name' => substr($receiverLastName, 0, 50),
            'email' => $receiver['email'] ?? ($senderEmail ?? 'receiver@freighteva.com'),
            'mobile' => !empty($receiver['phone']) ? $receiver['phone'] : '+10000000000',
            'address' => $receiver['address'] ?? 'Destination Address',
            'city' => !empty($receiver['city']) ? $receiver['city'] : 'Destination City',
            'zip_code' => !empty($receiver['postal_code']) ? substr($receiver['postal_code'], 0, 10) : '00000',
            'country_id' => $quote['destination_country_id'] ?? 161,
            'zone_id' => 1,
            'company' => !empty($receiver['company']) ? substr($receiver['company'], 0, 45) : null,
        ]);

        // 5. Create Shipment Record in Database
        $shipment = Shipment::create([
            'tenant_id' => $quote['tenant_id'] ?? 1,
            'origin' => $originString,
            'destination' => $destString,
            'weight' => (float)($quote['weight_kg'] ?? 1.0),
            'mode' => $quote['mode'] ?? 'air',
            'carrier' => $carrierName,
            'status' => 'booked',
            'payment_status' => 'successful',
            'date_paid' => now()->toDateString(),
            'invoice_prefix' => $invoicePrefix,
            'invoice_no' => $invoiceNo,
            'awb_number' => $awbNumber,
            'shipment_receiver_id' => $shipmentReceiver->id,
            'shipment_sender_id' => $shipmentSender->id,
            'freight_id' => str_contains(strtolower($quote['mode'] ?? ''), 'ocean') ? 1 : 2,
            'amount' => $basePrice,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'insurance_value' => $protectionFee,
            'handling_fee' => 0.00,
            'pickup_date' => $sender['pickup_date'] ?? now()->addDay()->toDateString(),
            'pickup_time_slot' => $sender['pickup_time_slot'] ?? '09:00 AM - 01:00 PM',
            'pickup_type' => 'Door-to-door',
            'freight_mode' => $quote['mode'] ?? 'air',
            'user_id' => $customerUser ? $customerUser->id : (auth()->id() ?: 1),
        ]);

        // 6. Instantiate Core Tracking Entity (Triggers TrackingObserver -> TrackingHistory)
        try {
            $trackingStatus = \App\Models\TrackingStatus::where('name', 'Shipment Booked and Manifested')->first();
            if (!$trackingStatus) {
                $trackingStatus = \App\Models\TrackingStatus::create([
                    'name' => 'Shipment Booked and Manifested',
                ]);
            }

            $tracking = \App\Models\Tracking::create([
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->awb_number,
                'tracking_status_id' => $trackingStatus->id,
                'tenant_id' => $shipment->tenant_id,
                'external_tracking_status' => 'BOOKED',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Tracking instantiation warning: ' . $e->getMessage());
        }

        // Record initial tracking event
        $shipment->recordTrackingEvent(
            status: 'BOOKED',
            title: 'Booking Confirmed',
            description: 'Shipment booking confirmed and payment captured on Freighteva platform via Stripe. Transmitted to partner CRM for fulfillment.',
            location: $shipment->origin,
            actorType: 'customer',
            metadata: [
                'service_name' => $quote['service_name'] ?? 'Freighteva Service',
                'protection_plan' => $protectionPlan,
                'payment_intent_id' => $paymentIntentId,
                'tracking_id' => isset($tracking) ? $tracking->id : null,
            ]
        );

        // Record Marketplace Audit Log
        $this->auditService->logBookingCreated($shipment, $quote);

        return response()->json([
            'success' => true,
            'message' => 'Shipment booking confirmed successfully on Freighteva!',
            'data' => [
                'booking_id' => $shipment->id,
                'awb_number' => $shipment->awb_number,
                'invoice_number' => $shipment->invoice_prefix . '-' . $shipment->invoice_no,
                'service_name' => $quote['service_name'] ?? 'Freighteva Service',
                'fulfillment_carrier' => $carrierName,
                'origin' => $shipment->origin,
                'destination' => $shipment->destination,
                'weight_kg' => $shipment->weight,
                'transit_days' => $quote['transit_days'] ?? '3-5 days',
                'base_price' => $basePrice,
                'protection_plan' => $protectionPlan,
                'protection_fee' => $protectionFee,
                'total_paid' => $totalAmount,
                'currency' => $currency,
                'status' => 'CONFIRMED',
                'payment_status' => 'PAID',
                'pickup_schedule' => [
                    'date' => $shipment->pickup_date,
                    'time_slot' => $shipment->pickup_time_slot,
                ],
                'created_at' => $shipment->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Error message translation helper.
     */
    protected function getVerificationErrorMessage(string $reason): string
    {
        return match ($reason) {
            'QUOTE_EXPIRED' => 'Your 15-minute rate quote lock has expired. Please refresh live quotes to re-lock current rates.',
            'SIGNATURE_VERIFICATION_FAILED' => 'Invalid rate security signature. Price tampering detected.',
            'MALFORMED_TOKEN_STRUCTURE' => 'Malformed booking token provided.',
            default => 'Quote rate lock could not be verified. Please search again.',
        };
    }
}
