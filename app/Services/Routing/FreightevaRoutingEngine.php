<?php

namespace App\Services\Routing;

use App\Models\MerchantShippingCapability;
use App\Models\Tenant;
use InvalidArgumentException;

class FreightevaRoutingEngine
{
    protected GeocodingService $geocodingService;

    public function __construct(GeocodingService $geocodingService)
    {
        $this->geocodingService = $geocodingService;
    }

    /**
     * Find and rank the best freight merchants for a shipment request.
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     */
    public function matchMerchants(array $params): array
    {
        $pickupPostcode = trim($params['pickup_postcode'] ?? '');
        $pickupCountry = strtoupper(trim($params['pickup_country'] ?? 'US'));
        $pickupAddress = trim($params['pickup_address'] ?? '');
        $destinationCountry = strtoupper(trim($params['destination_country'] ?? ''));
        $destinationCity = trim($params['destination_city'] ?? '');
        $freightMode = strtolower(trim($params['freight_mode'] ?? 'air'));
        $weightKg = (float) ($params['weight_kg'] ?? 1.0);
        $serviceType = strtolower(trim($params['service_type'] ?? 'pickup'));

        if (empty($pickupPostcode)) {
            throw new InvalidArgumentException("Pickup postcode or ZIP is required.");
        }

        if (empty($destinationCountry)) {
            throw new InvalidArgumentException("Destination country is required.");
        }

        if ($weightKg <= 0) {
            throw new InvalidArgumentException("Weight must be greater than 0 kg.");
        }

        // 1. Resolve Customer Location & Freighteva Zone
        $customerLoc = $this->geocodingService->resolveLocation($pickupPostcode, $pickupCountry, $pickupAddress);
        $custLat = (float) $customerLoc['lat'];
        $custLng = (float) $customerLoc['lng'];
        $custZone = $customerLoc['zone'];
        $custCity = $customerLoc['city'];

        // 2. Hard Capability Gates: Query eligible merchant shipping capabilities
        $capabilities = MerchantShippingCapability::with('tenant')
            ->servingRoute($destinationCountry, $freightMode)
            ->where('min_weight_kg', '<=', $weightKg)
            ->where('max_weight_kg', '>=', $weightKg)
            ->get();

        $scoredCandidates = [];

        foreach ($capabilities as $cap) {
            $tenant = $cap->tenant;
            if (!$tenant || (!$tenant->active && $tenant->status !== 'active')) {
                continue;
            }

            // Fallback default coordinates if not yet set on tenant
            $merchantLat = $tenant->latitude ? (float) $tenant->latitude : $custLat;
            $merchantLng = $tenant->longitude ? (float) $tenant->longitude : $custLng;
            $merchantZone = $tenant->zone_code ?: $custZone;
            $merchantMetro = $tenant->metro_area ?: $custCity;

            // 3. Compute Proximity (Haversine Distance in km)
            $distanceKm = $this->calculateHaversineDistance($custLat, $custLng, $merchantLat, $merchantLng);

            // 4. Classify Progressive Proximity Tier
            $searchTier = $this->determineSearchTier($distanceKm, $custZone, $merchantZone, $custCity, $merchantMetro);

            // 5. Compute Factor Scores (0 - 100)
            // Proximity Score (35% weight)
            $proximityScore = max(10, 100 - ($distanceKm * 1.5));

            // Route Fit Score (25% weight)
            $corridorTier = $cap->corridor_specialization_tier ?: 3;
            $routeFitScore = match ($corridorTier) {
                5 => 100.0,
                4 => 90.0,
                3 => 75.0,
                2 => 60.0,
                default => 45.0,
            };

            // Destination City Specialization Bonus
            if (!empty($cap->destination_city) && !empty($destinationCity)) {
                if (stripos($destinationCity, $cap->destination_city) !== false) {
                    $routeFitScore = min(100.0, $routeFitScore + 10.0);
                }
            }

            // Price / Value Score (15% weight)
            $ratePerKg = (float) $cap->base_rate_per_kg;
            $estimatedPrice = round($weightKg * $ratePerKg, 2);
            $priceScore = max(20.0, min(100.0, 120.0 - ($ratePerKg * 4.0)));

            // Capacity & Availability (10% weight)
            $successRate = $tenant->booking_success_rate ? (float) $tenant->booking_success_rate : 98.0;
            $capacityScore = min(100.0, $successRate);

            // Merchant Performance & Rating (10% weight)
            $ratingScoreVal = $tenant->rating_score ? (float) $tenant->rating_score : 4.8;
            $performanceScore = ($ratingScoreVal / 5.0) * 100.0;

            // Customer Preference (5% weight)
            $preferenceScore = 100.0;
            if ($serviceType === 'pickup' && !$cap->pickup_available) {
                $preferenceScore = 50.0;
            } elseif ($serviceType === 'dropoff' && !$cap->dropoff_available) {
                $preferenceScore = 50.0;
            }

            // 6. Calculate Composite Score
            $compositeScore = round(
                (0.35 * $proximityScore) +
                (0.25 * $routeFitScore) +
                (0.15 * $priceScore) +
                (0.10 * $capacityScore) +
                (0.10 * $performanceScore) +
                (0.05 * $preferenceScore),
                2
            );

            // Resolve Tenant Domain & Booking URL
            $tenantDomain = !empty($tenant->custom_domain) ? "https://{$tenant->custom_domain}" : "https://{$tenant->subdomain}.freightmata.com";
            $bookingUrl = "{$tenantDomain}/shipment/create";

            $scoredCandidates[] = [
                'id' => $tenant->id,
                'name' => $tenant->company_name ?: ($tenant->name ?: $tenant->subdomain),
                'subdomain' => $tenant->subdomain,
                'custom_domain' => $tenant->custom_domain,
                'base_url' => $tenantDomain,
                'booking_url' => $bookingUrl,
                'distance_km' => round($distanceKm, 1),
                'search_tier' => $searchTier,
                'routing_score' => $compositeScore,
                'rating' => $ratingScoreVal,
                'freight_mode' => strtoupper($cap->freight_mode),
                'rate_per_kg' => $ratePerKg,
                'estimated_price' => $estimatedPrice,
                'currency' => 'USD',
                'transit_time' => "{$cap->min_transit_days}-{$cap->max_transit_days} Business Days",
                'pickup_available' => (bool) $cap->pickup_available,
                'dropoff_available' => (bool) $cap->dropoff_available,
                'is_verified' => (bool) ($tenant->is_verified ?? true),
                'metrics' => [
                    'proximity_score' => round($proximityScore, 1),
                    'route_fit_score' => round($routeFitScore, 1),
                    'price_score' => round($priceScore, 1),
                    'capacity_score' => round($capacityScore, 1),
                    'performance_score' => round($performanceScore, 1),
                ]
            ];
        }

        // Sort candidates descending by routing score
        usort($scoredCandidates, fn($a, $b) => $b['routing_score'] <=> $a['routing_score']);

        if (empty($scoredCandidates)) {
            return [
                'customer_location' => [
                    'postcode' => $pickupPostcode,
                    'city' => $custCity,
                    'zone' => $custZone,
                    'country' => $pickupCountry,
                    'coordinates' => ['lat' => $custLat, 'lng' => $custLng],
                ],
                'match_status' => 'no_merchants_found',
                'message' => "No verified merchants currently service {$destinationCountry} for {$freightMode} freight in this weight class.",
                'primary_merchant' => null,
                'alternative_merchants' => [],
            ];
        }

        $primary = $scoredCandidates[0];
        $primary['badge'] = 'Primary Recommended Partner (Best Match)';

        $alternatives = array_slice($scoredCandidates, 1, 2);

        return [
            'customer_location' => [
                'postcode' => $pickupPostcode,
                'city' => $custCity,
                'zone' => $custZone,
                'country' => $pickupCountry,
                'coordinates' => ['lat' => $custLat, 'lng' => $custLng],
            ],
            'match_status' => 'matched',
            'total_eligible_merchants' => count($scoredCandidates),
            'primary_merchant' => $primary,
            'alternative_merchants' => $alternatives,
        ];
    }

    /**
     * Calculate Haversine distance in kilometers between two lat/lng points.
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Classify the search tier hierarchy.
     */
    protected function determineSearchTier(float $distanceKm, string $custZone, string $merchantZone, string $custMetro, string $merchantMetro): string
    {
        if ($distanceKm <= 15.0) {
            return 'TIER_1_LOCAL';
        }
        if ($distanceKm <= 40.0) {
            return 'TIER_2_EXTENDED_LOCAL';
        }
        if (!empty($custMetro) && strcasecmp($custMetro, $merchantMetro) === 0) {
            return 'TIER_3_METRO_AREA';
        }
        if (!empty($custZone) && strcasecmp($custZone, $merchantZone) === 0) {
            return 'TIER_4_FREIGHTEVA_ZONE';
        }
        return 'TIER_5_NATIONAL';
    }
}
