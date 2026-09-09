<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Routing\FreightevaRoutingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class FreightevaRoutingController extends Controller
{
    protected FreightevaRoutingEngine $routingEngine;

    public function __construct(FreightevaRoutingEngine $routingEngine)
    {
        $this->routingEngine = $routingEngine;
    }

    /**
     * Match and rank freight merchants for a shipment request.
     */
    public function matchMerchants(Request $request): JsonResponse
    {
        $request->validate([
            'pickup_postcode' => 'required|string',
            'destination_country' => 'required|string|size:2',
            'pickup_country' => 'nullable|string|size:2',
            'pickup_address' => 'nullable|string',
            'destination_city' => 'nullable|string',
            'freight_mode' => 'nullable|string|in:air,ocean,both',
            'weight_kg' => 'nullable|numeric|min:0.1',
            'service_type' => 'nullable|string|in:pickup,dropoff',
        ]);

        try {
            $result = $this->routingEngine->matchMerchants($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Freight merchants matched successfully.',
                'data' => $result,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Freighteva Routing Engine Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while matching freight merchants.',
            ], 500);
        }
    }

    /**
     * Render interactive Swagger UI documentation.
     */
    public function swaggerUi(): View
    {
        return view('swagger.index');
    }

    /**
     * Return OpenAPI 3.0 JSON specification for all Freighteva APIs.
     */
    public function swaggerSpec(): JsonResponse
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Freighteva Complete API Suite',
                'description' => 'Comprehensive API documentation for Freighteva Global Logistics: Location-Aware Freight Matchmaking, Multi-Merchant Rate Search, Booking Management, and Customer Reviews.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Freighteva Engineering Team',
                    'url' => 'https://freightmata.com',
                ],
            ],
            'servers' => [
                [
                    'url' => url('/'),
                    'description' => 'Current Environment Server',
                ],
            ],
            'tags' => [
                ['name' => 'Location-Aware Routing Engine', 'description' => 'Intelligent proximity matchmaking & multi-factor scoring algorithm.'],
                ['name' => 'Search & Rate Comparison', 'description' => 'Autocomplete, multi-tenant rate comparisons, and popular shipping routes.'],
                ['name' => 'Shipments & Bookings', 'description' => 'Shipment booking, tracking, and details management.'],
                ['name' => 'Reviews & Ratings', 'description' => 'Merchant verification reviews and feedback.'],
            ],
            'paths' => [
                '/api/v1/routing/match-merchants' => [
                    'post' => [
                        'summary' => 'Find & Rank Nearest Verified Freight Merchants',
                        'description' => 'Evaluates hard capability gates (corridor, Air/Ocean freight mode, capacity), computes proximity, and applies a multi-factor composite scoring engine to return a Primary Recommended Partner and Alternative Options.',
                        'tags' => ['Location-Aware Routing Engine'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['pickup_postcode', 'destination_country'],
                                        'properties' => [
                                            'pickup_country' => ['type' => 'string', 'example' => 'US', 'description' => 'ISO 2-letter origin country code.'],
                                            'pickup_postcode' => ['type' => 'string', 'example' => '60601', 'description' => 'Customer pickup postal/ZIP code.'],
                                            'pickup_address' => ['type' => 'string', 'example' => 'Chicago, IL', 'description' => 'Optional pickup address.'],
                                            'destination_country' => ['type' => 'string', 'example' => 'NG', 'description' => 'ISO 2-letter destination country code.'],
                                            'destination_city' => ['type' => 'string', 'example' => 'Lagos', 'description' => 'Optional destination city.'],
                                            'freight_mode' => ['type' => 'string', 'enum' => ['air', 'ocean'], 'example' => 'air', 'description' => 'Requested freight mode.'],
                                            'weight_kg' => ['type' => 'number', 'format' => 'float', 'example' => 10.5, 'description' => 'Package weight in kg.'],
                                            'service_type' => ['type' => 'string', 'enum' => ['pickup', 'dropoff'], 'example' => 'pickup', 'description' => 'Service preference.'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successfully matched and ranked eligible merchants.',
                                'content' => [
                                    'application/json' => [
                                        'example' => [
                                            'status' => 'success',
                                            'message' => 'Freight merchants matched successfully.',
                                            'data' => [
                                                'customer_location' => [
                                                    'postcode' => '60601',
                                                    'city' => 'Chicago',
                                                    'zone' => 'US-ZONE-2-MIDWEST',
                                                    'country' => 'US',
                                                    'coordinates' => ['lat' => 41.8853, 'lng' => -87.6216],
                                                ],
                                                'match_status' => 'matched',
                                                'total_eligible_merchants' => 3,
                                                'primary_merchant' => [
                                                    'id' => 2,
                                                    'name' => 'Enitan Shipping Inc',
                                                    'subdomain' => 'oneil',
                                                    'badge' => 'Primary Recommended Partner (Best Match)',
                                                    'distance_km' => 0.4,
                                                    'search_tier' => 'TIER_1_LOCAL',
                                                    'routing_score' => 96.51,
                                                    'rating' => 4.9,
                                                    'freight_mode' => 'AIR',
                                                    'rate_per_kg' => 10.00,
                                                    'estimated_price' => 100.00,
                                                    'currency' => 'USD',
                                                    'transit_time' => '3-5 Business Days',
                                                    'booking_url' => 'https://oneil.freightmata.com/shipment/create',
                                                ],
                                                'alternative_merchants' => [
                                                    [
                                                        'id' => 1,
                                                        'name' => 'PostDoorman UK Logistics',
                                                        'subdomain' => 'abidexpress',
                                                        'distance_km' => 6351.9,
                                                        'search_tier' => 'TIER_5_NATIONAL',
                                                        'routing_score' => 65.95,
                                                        'estimated_price' => 85.00,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '422' => ['description' => 'Validation error.'],
                        ],
                    ],
                ],

                '/api/search/autocomplete' => [
                    'get' => [
                        'summary' => 'Search Origin & Destination Location Autocomplete',
                        'description' => 'Returns matching countries, capitals, and logistics zones for real-time form suggestions.',
                        'tags' => ['Search & Rate Comparison'],
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'Nig'], 'description' => 'Search query string.'],
                            ['name' => 'type', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['from', 'to'], 'default' => 'from'], 'description' => 'Origin (from) or Destination (to).'],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'List of matching location suggestions.',
                                'content' => [
                                    'application/json' => [
                                        'example' => [
                                            'success' => true,
                                            'data' => [
                                                ['id' => 156, 'city' => 'Abuja', 'country' => 'Nigeria', 'iso_code' => 'NG'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                '/api/search/rates' => [
                    'post' => [
                        'summary' => 'Multi-Merchant Shipping Rate Comparison',
                        'description' => 'Compares rates across all active logistics partners based on route, weight, dimensions, and freight type.',
                        'tags' => ['Search & Rate Comparison'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['from_country', 'to_country', 'weight'],
                                        'properties' => [
                                            'from_country' => ['type' => 'string', 'example' => 'United States'],
                                            'to_country' => ['type' => 'string', 'example' => 'Nigeria'],
                                            'weight' => ['type' => 'number', 'example' => 10],
                                            'type' => ['type' => 'string', 'enum' => ['air', 'ocean', 'all'], 'example' => 'air'],
                                            'length' => ['type' => 'number', 'example' => 20],
                                            'width' => ['type' => 'number', 'example' => 20],
                                            'height' => ['type' => 'number', 'example' => 20],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'List of calculated rates across merchants.',
                                'content' => [
                                    'application/json' => [
                                        'example' => [
                                            'success' => true,
                                            'data' => [
                                                [
                                                    'tenant_id' => 2,
                                                    'company_name' => 'Enitan Shipping Inc',
                                                    'mode' => 'air',
                                                    'price' => 100.00,
                                                    'currency' => 'USD',
                                                    'transit_time' => '3-5 business days',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                '/api/public/popular-routes' => [
                    'get' => [
                        'summary' => 'Get Popular Trade & Freight Corridors',
                        'description' => 'Returns top global freight routes with starting prices and transit time indicators.',
                        'tags' => ['Search & Rate Comparison'],
                        'responses' => [
                            '200' => ['description' => 'List of popular trade corridors.'],
                        ],
                    ],
                ],

                '/api/shipments' => [
                    'get' => [
                        'summary' => 'List User Shipments',
                        'description' => 'Returns all shipments booked by the authenticated customer.',
                        'tags' => ['Shipments & Bookings'],
                        'responses' => [
                            '200' => ['description' => 'List of customer shipments.'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Book a New Shipment',
                        'description' => 'Creates a new shipment booking and generates invoice/tracking credentials.',
                        'tags' => ['Shipments & Bookings'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['tenant_id', 'origin', 'destination', 'weight', 'price', 'mode', 'carrier'],
                                        'properties' => [
                                            'tenant_id' => ['type' => 'integer', 'example' => 2],
                                            'origin' => ['type' => 'string', 'example' => 'Chicago, US'],
                                            'destination' => ['type' => 'string', 'example' => 'Lagos, NG'],
                                            'weight' => ['type' => 'number', 'example' => 10],
                                            'price' => ['type' => 'number', 'example' => 100.00],
                                            'mode' => ['type' => 'string', 'example' => 'air'],
                                            'carrier' => ['type' => 'string', 'example' => 'Air Freight'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Shipment booked successfully.'],
                        ],
                    ],
                ],

                '/api/shipments/{id}' => [
                    'get' => [
                        'summary' => 'Get Shipment Details',
                        'description' => 'Retrieve shipment status, invoice number, and tracking information.',
                        'tags' => ['Shipments & Bookings'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'example' => 1]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Shipment details.'],
                            '404' => ['description' => 'Shipment not found.'],
                        ],
                    ],
                ],

                '/api/reviews/{tenant_id}' => [
                    'get' => [
                        'summary' => 'Get Merchant Reviews',
                        'description' => 'Fetches verified customer ratings, reviews, and average score for a merchant.',
                        'tags' => ['Reviews & Ratings'],
                        'parameters' => [
                            ['name' => 'tenant_id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'example' => 2]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of merchant reviews and average rating.'],
                        ],
                    ],
                ],

                '/api/reviews' => [
                    'post' => [
                        'summary' => 'Submit Shipment Review',
                        'description' => 'Submit a rating (1-5 stars) and review comment for a completed shipment.',
                        'tags' => ['Reviews & Ratings'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['tenant_id', 'shipment_id', 'rating'],
                                        'properties' => [
                                            'tenant_id' => ['type' => 'integer', 'example' => 2],
                                            'shipment_id' => ['type' => 'integer', 'example' => 1],
                                            'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'example' => 5],
                                            'comment' => ['type' => 'string', 'example' => 'Excellent fast delivery to Lagos!'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Review submitted successfully.'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($spec, 200);
    }
}
