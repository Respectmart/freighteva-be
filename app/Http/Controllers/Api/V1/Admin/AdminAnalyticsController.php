<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAuditLog;
use App\Services\Analytics\MarketplaceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected MarketplaceAnalyticsService $analyticsService
    ) {}

    /**
     * Platform-wide Executive Analytics Overview.
     * GET /api/v1/admin/analytics/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $data = $this->analyticsService->getAdminOverview($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Detailed trade corridors intelligence.
     * GET /api/v1/admin/analytics/corridors
     */
    public function corridors(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $limit = (int)$request->query('limit', 10);

        $data = $this->analyticsService->getCorridorAnalytics($dateFrom, $dateTo, $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Merchant leaderboard & ranking metrics.
     * GET /api/v1/admin/analytics/merchants
     */
    public function merchants(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $limit = (int)$request->query('limit', 20);

        $data = $this->analyticsService->getMerchantLeaderboard($dateFrom, $dateTo, $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Marketplace Audit Trail Viewer.
     * GET /api/v1/admin/audit-logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = MarketplaceAuditLog::with(['tenant']);

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->query('tenant_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->query('event_type'));
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->query('actor_type'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->query('date_to'));
        }

        $perPage = (int)$request->query('per_page', 20);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
