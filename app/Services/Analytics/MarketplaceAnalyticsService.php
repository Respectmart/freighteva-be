<?php

namespace App\Services\Analytics;

use App\Models\MarketplaceAuditLog;
use App\Models\MerchantBookingException;
use App\Models\MerchantPerformanceLog;
use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class MarketplaceAnalyticsService
{
    /**
     * Get platform-wide executive analytics overview for administrators.
     */
    public function getAdminOverview(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $shipmentQuery = Shipment::query();
        $auditQuery = MarketplaceAuditLog::query();
        $exceptionQuery = MerchantBookingException::query();

        if ($dateFrom) {
            $shipmentQuery->where('created_at', '>=', $dateFrom);
            $auditQuery->where('created_at', '>=', $dateFrom);
            $exceptionQuery->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $shipmentQuery->where('created_at', '<=', $dateTo);
            $auditQuery->where('created_at', '<=', $dateTo);
            $exceptionQuery->where('created_at', '<=', $dateTo);
        }

        // 1. Gross Marketplace Metrics
        $totalBookings = (clone $shipmentQuery)->count();
        $totalGmv = (float)(clone $shipmentQuery)->sum('total_amount');
        $averageOrderValue = $totalBookings > 0 ? round($totalGmv / $totalBookings, 2) : 0.0;

        // 2. Status Breakdown
        $statusBreakdown = (clone $shipmentQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 3. Freight Mode Share
        $modes = (clone $shipmentQuery)
            ->select('mode', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as gmv'), DB::raw('sum(weight) as total_weight'))
            ->groupBy('mode')
            ->get();

        $modeShare = [];
        foreach ($modes as $m) {
            $modeKey = strtolower($m->mode ?: 'air');
            $modeShare[$modeKey] = [
                'count' => (int)$m->count,
                'gmv' => round((float)$m->gmv, 2),
                'total_weight_kg' => round((float)$m->total_weight, 2),
                'percentage' => $totalBookings > 0 ? round(($m->count / $totalBookings) * 100, 1) : 0,
            ];
        }

        // 4. Conversion Funnel Analytics
        $quotesGenerated = (clone $auditQuery)->where('event_type', 'quote_generated')->count();
        $conversionRate = $quotesGenerated > 0 ? round(($totalBookings / $quotesGenerated) * 100, 2) : 0.0;
        $deliveredCount = $statusBreakdown['delivered'] ?? 0;
        $fulfillmentRate = $totalBookings > 0 ? round(($deliveredCount / $totalBookings) * 100, 2) : 0.0;

        // 5. Exceptions & Exception Engine Analytics
        $totalExceptions = (clone $exceptionQuery)->count();
        $reassignedCount = (clone $exceptionQuery)->where('resolution_status', 'REASSIGNED')->count();
        $unfulfillableCount = (clone $exceptionQuery)->where('resolution_status', 'REFUND_PENDING')->count();
        $reassignmentRate = $totalExceptions > 0 ? round(($reassignedCount / $totalExceptions) * 100, 1) : 0.0;

        $rejectionReasons = (clone $exceptionQuery)
            ->select('reason_code', DB::raw('count(*) as count'))
            ->groupBy('reason_code')
            ->pluck('count', 'reason_code')
            ->toArray();

        // 6. Top Trade Corridors
        $topCorridors = (clone $shipmentQuery)
            ->select('origin', 'destination', DB::raw('count(*) as total_shipments'), DB::raw('sum(total_amount) as total_revenue'))
            ->groupBy('origin', 'destination')
            ->orderByDesc('total_shipments')
            ->limit(5)
            ->get()
            ->map(function ($c) {
                return [
                    'corridor' => "{$c->origin} → {$c->destination}",
                    'origin' => $c->origin,
                    'destination' => $c->destination,
                    'total_shipments' => (int)$c->total_shipments,
                    'total_revenue' => round((float)$c->total_revenue, 2),
                ];
            });

        return [
            'gross_metrics' => [
                'total_bookings' => $totalBookings,
                'gross_marketplace_volume' => round($totalGmv, 2),
                'average_order_value' => $averageOrderValue,
                'currency' => 'USD',
            ],
            'status_distribution' => $statusBreakdown,
            'mode_distribution' => $modeShare,
            'funnel' => [
                'quotes_generated' => $quotesGenerated,
                'bookings_created' => $totalBookings,
                'deliveries_completed' => $deliveredCount,
                'quote_to_booking_conversion_rate' => $conversionRate,
                'fulfillment_rate' => $fulfillmentRate,
            ],
            'exception_metrics' => [
                'total_rejections' => $totalExceptions,
                'reassigned_count' => $reassignedCount,
                'unfulfillable_count' => $unfulfillableCount,
                'reassignment_success_rate' => $reassignmentRate,
                'rejection_reasons' => $rejectionReasons,
            ],
            'top_corridors' => $topCorridors,
            'timeframe' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }

    /**
     * Get detailed corridor and route intelligence.
     */
    public function getCorridorAnalytics(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $query = Shipment::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $corridors = $query
            ->select(
                'origin',
                'destination',
                'mode',
                DB::raw('count(*) as shipments_count'),
                DB::raw('sum(total_amount) as total_volume'),
                DB::raw('avg(total_amount) as avg_price'),
                DB::raw('sum(weight) as total_weight_kg')
            )
            ->groupBy('origin', 'destination', 'mode')
            ->orderByDesc('shipments_count')
            ->limit($limit)
            ->get();

        return [
            'total_corridors' => $corridors->count(),
            'corridors' => $corridors->map(function ($c) {
                return [
                    'route' => "{$c->origin} → {$c->destination}",
                    'mode' => $c->mode,
                    'shipments_count' => (int)$c->shipments_count,
                    'total_revenue' => round((float)$c->total_volume, 2),
                    'average_shipment_price' => round((float)$c->avg_price, 2),
                    'total_weight_kg' => round((float)$c->total_weight_kg, 2),
                ];
            }),
        ];
    }

    /**
     * Get Merchant Leaderboard and Reliability Rankings.
     */
    public function getMerchantLeaderboard(?string $dateFrom = null, ?string $dateTo = null, int $limit = 20): array
    {
        $merchants = Tenant::with(['endorsement'])->get();

        $ranked = $merchants->map(function (Tenant $tenant) use ($dateFrom, $dateTo) {
            $shipmentQuery = Shipment::where('tenant_id', $tenant->id);
            $rejectionQuery = MerchantBookingException::where('original_tenant_id', $tenant->id);

            if ($dateFrom) {
                $shipmentQuery->where('created_at', '>=', $dateFrom);
                $rejectionQuery->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $shipmentQuery->where('created_at', '<=', $dateTo);
                $rejectionQuery->where('created_at', '<=', $dateTo);
            }

            $totalShipments = $shipmentQuery->count();
            $totalRevenue = (float)$shipmentQuery->sum('total_amount');
            $rejections = $rejectionQuery->count();
            $totalOffers = $totalShipments + $rejections;

            $acceptanceRate = $totalOffers > 0 ? round(($totalShipments / $totalOffers) * 100, 1) : 100.0;
            $deliveredCount = (clone $shipmentQuery)->where('status', 'delivered')->count();

            return [
                'merchant_id' => $tenant->id,
                'company_name' => $tenant->company_name ?: $tenant->name,
                'is_endorsed' => (bool)($tenant->endorsement?->is_endorsed ?? false),
                'endorsement_tier' => $tenant->endorsement?->tier ?? 1,
                'rating' => (float)($tenant->rating_score ?: 4.5),
                'total_shipments' => $totalShipments,
                'total_revenue' => round($totalRevenue, 2),
                'delivered_count' => $deliveredCount,
                'rejections_count' => $rejections,
                'acceptance_rate' => $acceptanceRate,
            ];
        });

        $sorted = $ranked->sortByDesc('total_revenue')->values()->take($limit);

        return [
            'total_merchants' => $sorted->count(),
            'leaderboard' => $sorted->toArray(),
        ];
    }

    /**
     * Get merchant-specific individual performance overview.
     */
    public function getMerchantOverview(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $tenant = Tenant::with(['endorsement'])->findOrFail($tenantId);

        $shipmentQuery = Shipment::where('tenant_id', $tenantId);
        $rejectionQuery = MerchantBookingException::where('original_tenant_id', $tenantId);
        $penaltyQuery = MerchantPerformanceLog::where('tenant_id', $tenantId);

        if ($dateFrom) {
            $shipmentQuery->where('created_at', '>=', $dateFrom);
            $rejectionQuery->where('created_at', '>=', $dateFrom);
            $penaltyQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $shipmentQuery->where('created_at', '<=', $dateTo);
            $rejectionQuery->where('created_at', '<=', $dateTo);
            $penaltyQuery->where('created_at', '<=', $dateTo);
        }

        $totalInbound = (clone $shipmentQuery)->count();
        $totalRevenue = (float)(clone $shipmentQuery)->sum('total_amount');
        $rejectionsCount = (clone $rejectionQuery)->count();
        $totalOffers = $totalInbound + $rejectionsCount;

        $acceptanceRate = $totalOffers > 0 ? round(($totalInbound / $totalOffers) * 100, 1) : 100.0;
        $rejectionRate = $totalOffers > 0 ? round(($rejectionsCount / $totalOffers) * 100, 1) : 0.0;

        $activeShipments = (clone $shipmentQuery)->whereIn('status', ['booked', 'merchant_received', 'accepted', 'pickup_scheduled', 'picked_up', 'in_transit', 'arrived'])->count();
        $deliveredShipments = (clone $shipmentQuery)->where('status', 'delivered')->count();

        $totalPenaltyPoints = (float)(clone $penaltyQuery)->sum('impact_score');

        $reasons = (clone $rejectionQuery)
            ->select('reason_code', DB::raw('count(*) as count'))
            ->groupBy('reason_code')
            ->pluck('count', 'reason_code')
            ->toArray();

        // Recent Audit Activity
        $recentAudit = MarketplaceAuditLog::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['event_type', 'entity_type', 'entity_id', 'created_at']);

        return [
            'merchant_info' => [
                'tenant_id' => $tenant->id,
                'company_name' => $tenant->company_name ?: $tenant->name,
                'rating_score' => (float)($tenant->rating_score ?: 4.5),
                'is_endorsed' => (bool)($tenant->endorsement?->is_endorsed ?? false),
                'endorsement_tier' => $tenant->endorsement?->tier ?? 1,
            ],
            'kpis' => [
                'total_bookings' => $totalInbound,
                'total_revenue' => round($totalRevenue, 2),
                'active_shipments' => $activeShipments,
                'delivered_shipments' => $deliveredShipments,
                'acceptance_rate' => $acceptanceRate,
                'rejection_rate' => $rejectionRate,
                'rejections_count' => $rejectionsCount,
                'total_penalty_score' => $totalPenaltyPoints,
            ],
            'rejection_reasons' => $reasons,
            'recent_activity' => $recentAudit,
        ];
    }
}
