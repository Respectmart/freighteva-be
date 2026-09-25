<?php

namespace App\Services\Recommendation;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class MerchantEligibilityService
{
    /**
     * Standardized Exclusion Reason Codes as specified in PRD Section 33.
     */
    public const REASON_NOT_ENDORSED = 'NOT_ENDORSED';
    public const REASON_ENDORSEMENT_SUSPENDED = 'ENDORSEMENT_SUSPENDED';
    public const REASON_ENDORSEMENT_EXPIRED = 'ENDORSEMENT_EXPIRED';
    public const REASON_MERCHANT_INACTIVE = 'MERCHANT_INACTIVE';
    public const REASON_ROUTE_NOT_SUPPORTED = 'ROUTE_NOT_SUPPORTED';
    public const REASON_MODE_NOT_SUPPORTED = 'MODE_NOT_SUPPORTED';
    public const REASON_WEIGHT_OUT_OF_BOUNDS = 'WEIGHT_OUT_OF_BOUNDS';
    public const REASON_NO_ACTIVE_RATE = 'NO_ACTIVE_RATE';

    /**
     * Evaluate all merchants against the requested route & endorsement gates.
     *
     * @param array $params [
     *   'origin_country_id' => int,
     *   'destination_country_id' => int,
     *   'origin_country_code' => string,
     *   'destination_country_code' => string,
     *   'mode' => string ('air' | 'ocean'),
     *   'weight_kg' => float,
     * ]
     * @return array ['eligible' => array, 'excluded' => array]
     */
    public function evaluateMerchants(array $params): array
    {
        $originCountryId = $params['origin_country_id'] ?? null;
        $destCountryId = $params['destination_country_id'] ?? null;
        $mode = strtolower(trim($params['mode'] ?? 'air')); // 'air' or 'ocean'
        $weightKg = (float)($params['weight_kg'] ?? 1.0);

        // Standardize mode string for database lookup
        $routeMode = str_contains($mode, 'ocean') ? 'ocean' : 'air';

        $allTenants = Tenant::with(['capabilities', 'endorsement'])->get();

        $eligible = [];
        $excluded = [];

        foreach ($allTenants as $tenant) {
            $evaluation = $this->evaluateSingleTenant($tenant, $originCountryId, $destCountryId, $routeMode, $weightKg);

            if ($evaluation['is_eligible']) {
                $eligible[] = [
                    'tenant' => $tenant,
                    'route' => $evaluation['route'],
                    'capability' => $evaluation['capability'],
                    'calculated_price' => $evaluation['calculated_price'],
                    'transit_days' => $evaluation['transit_days'],
                    'min_days' => $evaluation['min_days'],
                    'max_days' => $evaluation['max_days'],
                    'base_rate' => $evaluation['base_rate'],
                ];
            } else {
                $excluded[] = [
                    'tenant_id' => $tenant->id,
                    'company_name' => $tenant->company_name ?: $tenant->name,
                    'reason_code' => $evaluation['reason_code'],
                    'reason_description' => $evaluation['reason_description'],
                ];
            }
        }

        return [
            'eligible' => $eligible,
            'excluded' => $excluded,
            'total_evaluated' => count($allTenants),
            'eligible_count' => count($eligible),
            'excluded_count' => count($excluded),
        ];
    }

    /**
     * Evaluate a single tenant against endorsement and route criteria.
     */
    public function evaluateSingleTenant(Tenant $tenant, ?int $originCountryId, ?int $destCountryId, string $routeMode, float $weightKg): array
    {
        // 1. Check Active Status Gate
        if (!$tenant->active && $tenant->status !== 'active') {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_MERCHANT_INACTIVE,
                'reason_description' => 'Merchant account is not active.',
            ];
        }

        // 2. Check Endorsement Gate (AC-01 / AC-18 / AC-19) via isolated endorsement relation
        $endorsement = $tenant->endorsement;

        if (!$endorsement || !$endorsement->is_endorsed) {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_NOT_ENDORSED,
                'reason_description' => 'Merchant has not received official Freighteva administrator endorsement.',
            ];
        }

        if ($endorsement->status === 'SUSPENDED') {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_ENDORSEMENT_SUSPENDED,
                'reason_description' => 'Merchant endorsement is currently suspended by administration.',
            ];
        }

        if ($endorsement->status !== 'ENDORSED') {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_NOT_ENDORSED,
                'reason_description' => 'Merchant endorsement status is ' . ($endorsement->status ?: 'unverified') . '.',
            ];
        }

        if ($endorsement->expires_at && $endorsement->expires_at->isPast()) {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_ENDORSEMENT_EXPIRED,
                'reason_description' => 'Merchant endorsement expired on ' . $endorsement->expires_at->toDateString() . '.',
            ];
        }

        // 3. Check Route Support Gate (AC-02)
        if (!$originCountryId || !$destCountryId) {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_ROUTE_NOT_SUPPORTED,
                'reason_description' => 'Origin or destination country not identified.',
            ];
        }

        $route = DB::table('shipment_routes')
            ->where('tenant_id', $tenant->id)
            ->where('sending_country_id', $originCountryId)
            ->where('receiving_country_id', $destCountryId)
            ->where('mode', $routeMode)
            ->first();

        if (!$route) {
            // Check if route exists for different mode
            $otherModeRoute = DB::table('shipment_routes')
                ->where('tenant_id', $tenant->id)
                ->where('sending_country_id', $originCountryId)
                ->where('receiving_country_id', $destCountryId)
                ->first();

            if ($otherModeRoute) {
                return [
                    'is_eligible' => false,
                    'reason_code' => self::REASON_MODE_NOT_SUPPORTED,
                    'reason_description' => "Merchant serves this corridor but not for {$routeMode} freight.",
                ];
            }

            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_ROUTE_NOT_SUPPORTED,
                'reason_description' => 'Merchant does not operate an approved route on this origin-destination corridor.',
            ];
        }

        // 4. Check Active Rate Gate
        $rate = (float)($route->shipping_rate ?? 0);
        if ($rate <= 0) {
            return [
                'is_eligible' => false,
                'reason_code' => self::REASON_NO_ACTIVE_RATE,
                'reason_description' => 'Merchant route does not have an active positive shipping rate configured.',
            ];
        }

        // 5. Check Optional Merchant Capability Bounds if configured
        $capability = $tenant->capabilities->first(function ($cap) use ($routeMode) {
            return $cap->is_active && ($cap->freight_mode === $routeMode || $cap->freight_mode === 'both');
        });

        if ($capability) {
            if ($weightKg < (float)$capability->min_weight_kg || $weightKg > (float)$capability->max_weight_kg) {
                return [
                    'is_eligible' => false,
                    'reason_code' => self::REASON_WEIGHT_OUT_OF_BOUNDS,
                    'reason_description' => "Shipment weight ({$weightKg} kg) is outside merchant supported range ({$capability->min_weight_kg}kg - {$capability->max_weight_kg}kg).",
                ];
            }
        }

        $minDays = (int)($route->min_delivery_day ?: ($capability->min_transit_days ?? 4));
        $maxDays = (int)($route->max_delivery_day ?: ($capability->max_transit_days ?? 8));
        $calculatedPrice = round($rate * $weightKg, 2);

        return [
            'is_eligible' => true,
            'route' => $route,
            'capability' => $capability,
            'calculated_price' => $calculatedPrice,
            'transit_days' => "{$minDays}-{$maxDays} days",
            'min_days' => $minDays,
            'max_days' => $maxDays,
            'base_rate' => $rate,
        ];
    }
}
