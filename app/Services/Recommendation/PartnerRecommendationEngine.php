<?php

namespace App\Services\Recommendation;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerRecommendationEngine
{
    protected MerchantEligibilityService $eligibilityService;
    protected QuoteTokenService $tokenService;

    public function __construct(
        MerchantEligibilityService $eligibilityService,
        QuoteTokenService $tokenService
    ) {
        $this->eligibilityService = $eligibilityService;
        $this->tokenService = $tokenService;
    }

    /**
     * Get top curated partner recommendations for the given route query.
     *
     * @param array $params [
     *   'origin_country_id' => int,
     *   'destination_country_id' => int,
     *   'origin_country_code' => string,
     *   'destination_country_code' => string,
     *   'origin_city' => string|null,
     *   'destination_city' => string|null,
     *   'mode' => string ('air' | 'ocean'),
     *   'weight_kg' => float,
     * ]
     * @return array
     */
    public function getRecommendations(array $params): array
    {
        $weightKg = max(0.1, (float)($params['weight_kg'] ?? 1.0));
        $mode = strtolower(trim($params['mode'] ?? 'air'));
        $routeMode = str_contains($mode, 'ocean') ? 'ocean' : 'air';

        // 1. Fetch Configurable Scoring Weights and Service Names
        $configs = $this->loadConfigurations();
        $weights = $configs['scoring_weights'];
        $serviceNames = $configs['service_names'];
        $ttlMinutes = (int)($configs['quote_ttl_minutes'] ?? 15);
        $maxRecommendations = (int)($configs['max_recommendations'] ?? 5);

        // 2. Evaluate Merchant Eligibility Hard Gates (Module 1)
        $evaluation = $this->eligibilityService->evaluateMerchants($params);
        $eligibleMerchants = $evaluation['eligible'];

        if (empty($eligibleMerchants)) {
            return [
                'recommendations' => [],
                'total_recommended' => 0,
                'quote_ttl_minutes' => $ttlMinutes,
                'search_criteria' => [
                    'origin_country_id' => $params['origin_country_id'] ?? null,
                    'destination_country_id' => $params['destination_country_id'] ?? null,
                    'mode' => $routeMode,
                    'weight_kg' => $weightKg,
                ],
                'diagnostics' => [
                    'total_evaluated' => $evaluation['total_evaluated'],
                    'eligible_count' => 0,
                    'excluded' => $evaluation['excluded'],
                ],
            ];
        }

        // 3. Score Eligible Merchants across all 5 Dimensions
        $scoredPool = $this->scoreMerchants($eligibleMerchants, $weights, $weightKg, $routeMode);

        // 4. Assign Distinctive Freighteva Service Identities (AC-06 / AC-07 / AC-08)
        $assignedRecommendations = $this->assignServiceIdentities(
            $scoredPool,
            $serviceNames,
            $maxRecommendations,
            $params,
            $ttlMinutes
        );

        return [
            'recommendations' => $assignedRecommendations,
            'total_recommended' => count($assignedRecommendations),
            'quote_ttl_minutes' => $ttlMinutes,
            'search_criteria' => [
                'origin_country_id' => $params['origin_country_id'] ?? null,
                'destination_country_id' => $params['destination_country_id'] ?? null,
                'mode' => $routeMode,
                'weight_kg' => $weightKg,
            ],
            'diagnostics' => [
                'total_evaluated' => $evaluation['total_evaluated'],
                'eligible_count' => $evaluation['eligible_count'],
                'excluded' => $evaluation['excluded'],
            ],
        ];
    }

    /**
     * Score each eligible merchant across Speed, Cost, Trust, Convenience, and Flexibility.
     */
    protected function scoreMerchants(array $eligibleMerchants, array $weights, float $weightKg, string $routeMode): Collection
    {
        $collection = collect($eligibleMerchants);

        // Extract min/max values for relative normalization
        $minPrice = $collection->min('calculated_price') ?: 1.0;
        $maxPrice = $collection->max('calculated_price') ?: 1.0;

        $minTransitDays = $collection->min('min_days') ?: 1;
        $maxTransitDays = $collection->max('max_days') ?: 10;

        return $collection->map(function ($item) use ($minPrice, $maxPrice, $minTransitDays, $maxTransitDays, $weights) {
            /** @var Tenant $tenant */
            $tenant = $item['tenant'];
            $route = $item['route'];
            $capability = $item['capability'];
            $calculatedPrice = (float)$item['calculated_price'];
            $minDays = (int)$item['min_days'];
            $maxDays = (int)$item['max_days'];

            // A. Speed Score (0 - 100): Lower days = higher score
            $avgDays = ($minDays + $maxDays) / 2.0;
            if ($maxTransitDays === $minTransitDays) {
                $speedScore = 95.0;
            } else {
                $speedScore = 100.0 - (($avgDays - $minTransitDays) / max(1, $maxTransitDays - $minTransitDays) * 60.0);
            }
            $speedScore = max(30.0, min(100.0, round($speedScore, 1)));

            // B. Cost Score (0 - 100): Lower price = higher score (lowest price gets 100)
            if ($calculatedPrice <= 0 || $minPrice <= 0) {
                $costScore = 80.0;
            } else {
                $costScore = ($minPrice / $calculatedPrice) * 100.0;
            }
            $costScore = max(25.0, min(100.0, round($costScore, 1)));

            // C. Trust & Reviews Score (0 - 100): Bayesian Dampened Rating
            $rawRating = (float)($tenant->rating_score ?: ($tenant->rating_avg ?: 4.5));
            $reviewCount = (int)($tenant->rating_count ?: 5);
            $endorsementTier = (int)($tenant->endorsement?->tier ?: 4);

            // Confidence dampening: rating * (n / (n + 3)) + tier_baseline * (3 / (n + 3))
            $confidence = $reviewCount / ($reviewCount + 3.0);
            $tierBaseline = 3.0 + ($endorsementTier * 0.35); // Tier 4 -> 4.4 / 5.0
            $effectiveRating = ($rawRating * $confidence) + ($tierBaseline * (1.0 - $confidence));
            $trustScore = max(40.0, min(100.0, round(($effectiveRating / 5.0) * 100.0, 1)));

            // D. Convenience Score (0 - 100): Doorstep pickup, dropoff, radius
            $convenienceScore = 40.0; // Base score
            if ($capability?->pickup_available ?? true) {
                $convenienceScore += 30.0;
            }
            if ($capability?->dropoff_available ?? true) {
                $convenienceScore += 15.0;
            }
            if (($tenant->service_radius_km ?? 25) >= 30) {
                $convenienceScore += 15.0;
            }
            $convenienceScore = max(30.0, min(100.0, round($convenienceScore, 1)));

            // E. Flexibility Score (0 - 100): Weight range, mode adaptability
            $flexibilityScore = 50.0;
            $maxWeight = (float)($capability?->max_weight_kg ?? 500.0);
            if ($maxWeight >= 500) {
                $flexibilityScore += 25.0;
            }
            if ($capability?->freight_mode === 'both') {
                $flexibilityScore += 25.0;
            }
            $flexibilityScore = max(30.0, min(100.0, round($flexibilityScore, 1)));

            // Composite Weighted Score
            $wSpeed = (float)($weights['speed'] ?? 25.0);
            $wCost = (float)($weights['cost'] ?? 25.0);
            $wTrust = (float)($weights['trust'] ?? 20.0);
            $wConv = (float)($weights['convenience'] ?? 15.0);
            $wFlex = (float)($weights['flexibility'] ?? 15.0);
            $totalWeight = $wSpeed + $wCost + $wTrust + $wConv + $wFlex;

            $compositeScore = (
                ($speedScore * $wSpeed) +
                ($costScore * $wCost) +
                ($trustScore * $wTrust) +
                ($convenienceScore * $wConv) +
                ($flexibilityScore * $wFlex)
            ) / max(1.0, $totalWeight);

            $compositeScore = round($compositeScore, 1);

            return array_merge($item, [
                'scores' => [
                    'composite' => $compositeScore,
                    'speed' => $speedScore,
                    'cost' => $costScore,
                    'trust' => $trustScore,
                    'convenience' => $convenienceScore,
                    'flexibility' => $flexibilityScore,
                ],
            ]);
        });
    }

    /**
     * Assign distinctive Freighteva service identities to the best candidate partners.
     */
    protected function assignServiceIdentities(
        Collection $scoredPool,
        array $serviceNames,
        int $maxRecommendations,
        array $params,
        int $ttlMinutes
    ): array {
        $identities = [
            'swift' => [
                'name' => $serviceNames['swift'] ?? 'Freighteva Swift',
                'badge' => '⚡ Fastest Delivery',
                'highlight' => 'Best for Speed',
                'tagline' => 'Express Priority Freight',
                'sort_key' => 'scores.speed',
            ],
            'savers' => [
                'name' => $serviceNames['savers'] ?? 'Freighteva Savers',
                'badge' => '💰 Best Value',
                'highlight' => 'Lowest Price',
                'tagline' => 'Economy Cost-Effective Cargo',
                'sort_key' => 'scores.cost',
            ],
            'trusted' => [
                'name' => $serviceNames['trusted'] ?? 'Freighteva Trusted',
                'badge' => '★ Most Trusted',
                'highlight' => 'Top Reliability',
                'tagline' => 'Premier Carrier SLA & Reviews',
                'sort_key' => 'scores.trust',
            ],
            'convenient' => [
                'name' => $serviceNames['convenient'] ?? 'Freighteva Convenient',
                'badge' => '🚪 Doorstep Pickup',
                'highlight' => 'Maximum Convenience',
                'tagline' => 'Full Door-to-Door Handling',
                'sort_key' => 'scores.convenience',
            ],
            'flexible' => [
                'name' => $serviceNames['flexible'] ?? 'Freighteva Flexible',
                'badge' => '📦 Flexible Cargo',
                'highlight' => 'Versatile Capacity',
                'tagline' => 'Accommodating Dimensions & Weight',
                'sort_key' => 'scores.flexibility',
            ],
        ];

        $assigned = [];
        $usedTenantIds = [];

        // Primary pass: Assign the #1 specialist for each distinct identity
        foreach ($identities as $key => $meta) {
            if (count($assigned) >= $maxRecommendations) {
                break;
            }

            // Find best unassigned candidate for this identity
            $candidate = $scoredPool
                ->reject(fn($item) => in_array($item['tenant']->id, $usedTenantIds))
                ->sortByDesc($meta['sort_key'])
                ->first();

            if ($candidate) {
                $usedTenantIds[] = $candidate['tenant']->id;
                $assigned[] = $this->buildRecommendationItem($candidate, $key, $meta, $params, $ttlMinutes);
            }
        }

        // Secondary fallback pass: If fewer than max recommendations and remaining unassigned candidates exist
        if (count($assigned) < $maxRecommendations) {
            $remaining = $scoredPool
                ->reject(fn($item) => in_array($item['tenant']->id, $usedTenantIds))
                ->sortByDesc('scores.composite');

            foreach ($remaining as $candidate) {
                if (count($assigned) >= $maxRecommendations) {
                    break;
                }
                $usedTenantIds[] = $candidate['tenant']->id;
                $fallbackMeta = [
                    'name' => 'Freighteva Standard Plus',
                    'badge' => '🛡️ Verified Partner',
                    'highlight' => 'Verified Quality',
                    'tagline' => 'Reliable Endorsed Carrier',
                ];
                $assigned[] = $this->buildRecommendationItem($candidate, 'standard', $fallbackMeta, $params, $ttlMinutes);
            }
        }

        // Sort final recommendations by composite score (highest overall first)
        usort($assigned, fn($a, $b) => $b['scores']['composite'] <=> $a['scores']['composite']);

        return $assigned;
    }

    /**
     * Build the public recommendation payload and generate cryptographic HMAC quote token.
     */
    protected function buildRecommendationItem(
        array $candidate,
        string $identityKey,
        array $identityMeta,
        array $params,
        int $ttlMinutes
    ): array {
        /** @var Tenant $tenant */
        $tenant = $candidate['tenant'];
        $route = $candidate['route'];
        $capability = $candidate['capability'];
        $calculatedPrice = (float)$candidate['calculated_price'];
        $baseRate = (float)$candidate['base_rate'];
        $weightKg = (float)($params['weight_kg'] ?? 1.0);
        $currency = $route->default_currency ?? 'USD';

        $quotePayload = [
            'tenant_id' => $tenant->id,
            'tenant_company' => $tenant->company_name ?: ($tenant->name ?: 'Freight Partner'),
            'route_id' => $route->id,
            'origin_country_id' => (int)($params['origin_country_id'] ?? $route->sending_country_id),
            'destination_country_id' => (int)($params['destination_country_id'] ?? $route->receiving_country_id),
            'mode' => $route->mode,
            'weight_kg' => $weightKg,
            'base_rate' => $baseRate,
            'calculated_price' => $calculatedPrice,
            'currency' => $currency,
            'service_name' => $identityMeta['name'],
            'service_key' => $identityKey,
            'transit_days' => $candidate['transit_days'],
            'min_days' => (int)$candidate['min_days'],
            'max_days' => (int)$candidate['max_days'],
            'scores' => $candidate['scores'],
        ];

        // Generate HMAC-SHA256 signed quote booking token
        $tokenData = $this->tokenService->generateToken($quotePayload, $ttlMinutes);

        return [
            'service_name' => $identityMeta['name'],
            'service_key' => $identityKey,
            'badge' => $identityMeta['badge'],
            'highlight' => $identityMeta['highlight'],
            'tagline' => $identityMeta['tagline'],
            'calculated_price' => $calculatedPrice,
            'base_rate' => $baseRate,
            'currency' => $currency,
            'transit_days' => $candidate['transit_days'],
            'min_days' => (int)$candidate['min_days'],
            'max_days' => (int)$candidate['max_days'],
            'freight_mode' => $route->mode,
            'scores' => $candidate['scores'],
            'booking_token' => $tokenData['token'],
            'quote_id' => $tokenData['quote_id'],
            'expires_at' => $tokenData['expires_at'],
            'expires_in_seconds' => $tokenData['expires_in_seconds'],
            'partner_attribution' => [
                'merchant_id' => $tenant->id,
                'company_name' => $tenant->company_name ?: ($tenant->name ?: 'Freighteva Verified Partner'),
                'rating_score' => (float)($tenant->rating_score ?: ($tenant->rating_avg ?: 4.8)),
                'rating_count' => (int)($tenant->rating_count ?: 0),
                'endorsement_tier' => (int)($tenant->endorsement?->tier ?: 4),
                'is_verified' => true,
                'is_endorsed' => true,
                'booking_success_rate' => (float)($tenant->booking_success_rate ?: 98.5),
                'metro_area' => $tenant->metro_area ?: null,
            ],
            'features' => [
                'pickup_available' => (bool)($capability?->pickup_available ?? true),
                'dropoff_available' => (bool)($capability?->dropoff_available ?? true),
                'live_tracking' => true,
                'insurance_included' => ((float)($route->insurance ?? 0) > 0),
                'freighteva_protection' => true,
            ],
        ];
    }

    /**
     * Load configurable weights, service names, and parameters from DB with hardcoded safe defaults.
     */
    protected function loadConfigurations(): array
    {
        $defaults = [
            'scoring_weights' => [
                'speed' => 25.0,
                'cost' => 25.0,
                'trust' => 20.0,
                'convenience' => 15.0,
                'flexibility' => 15.0,
            ],
            'service_names' => [
                'swift' => 'Freighteva Swift',
                'trusted' => 'Freighteva Trusted',
                'savers' => 'Freighteva Savers',
                'flexible' => 'Freighteva Flexible',
                'convenient' => 'Freighteva Convenient',
            ],
            'quote_ttl_minutes' => 15,
            'max_recommendations' => 5,
        ];

        try {
            $dbConfigs = DB::table('recommendation_configs')->pluck('value', 'key')->toArray();

            if (!empty($dbConfigs)) {
                if (isset($dbConfigs['scoring_weights'])) {
                    $decoded = json_decode($dbConfigs['scoring_weights'], true);
                    if (is_array($decoded)) {
                        $defaults['scoring_weights'] = $decoded;
                    }
                }
                if (isset($dbConfigs['service_names'])) {
                    $decoded = json_decode($dbConfigs['service_names'], true);
                    if (is_array($decoded)) {
                        $defaults['service_names'] = $decoded;
                    }
                }
                if (isset($dbConfigs['quote_ttl_minutes'])) {
                    $defaults['quote_ttl_minutes'] = (int)json_decode($dbConfigs['quote_ttl_minutes'], true);
                }
                if (isset($dbConfigs['max_recommendations'])) {
                    $defaults['max_recommendations'] = (int)json_decode($dbConfigs['max_recommendations'], true);
                }
            }
        } catch (\Throwable $e) {
            // Safe fallback to defaults if database query fails
        }

        return $defaults;
    }
}
