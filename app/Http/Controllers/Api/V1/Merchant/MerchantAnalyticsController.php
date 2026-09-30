<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Analytics\MarketplaceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantAnalyticsController extends Controller
{
    public function __construct(
        protected MarketplaceAnalyticsService $analyticsService
    ) {}

    /**
     * Resolve authenticated merchant tenant from session / request header.
     */
    protected function resolveMerchant(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Merchant-ID')
            ?: ($request->user()?->tenant_id ?: $request->input('tenant_id'));

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Merchant's Personal KPI Overview & Reliability Performance.
     * GET /api/v1/merchant/analytics/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);

        if (!$merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant authentication / tenant context required.',
            ], 401);
        }

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $data = $this->analyticsService->getMerchantOverview($merchant->id, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
