<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantEndorsement;
use App\Models\Tenant;
use App\Services\Recommendation\MerchantEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantEndorsementController extends Controller
{
    protected MerchantEligibilityService $eligibilityService;

    public function __construct(MerchantEligibilityService $eligibilityService)
    {
        $this->eligibilityService = $eligibilityService;
    }

    /**
     * List all merchants with endorsement relation and operational stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->with(['endorsement', 'capabilities'])
            ->withCount(['capabilities'])
            ->select('tenants.*')
            ->selectSub(function ($q) {
                $q->from('shipment_routes')
                  ->whereColumn('shipment_routes.tenant_id', 'tenants.id')
                  ->selectRaw('COUNT(id)');
            }, 'routes_count');

        if ($request->has('endorsement_status') && !empty($request->query('endorsement_status'))) {
            $status = strtoupper($request->query('endorsement_status'));
            $query->whereHas('endorsement', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        if ($request->has('is_endorsed')) {
            $isEndorsed = filter_var($request->query('is_endorsed'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('endorsement', function ($q) use ($isEndorsed) {
                $q->where('is_endorsed', $isEndorsed);
            });
        }

        $merchants = $query->orderBy('rating_avg', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $merchants,
        ]);
    }

    /**
     * Update or create a merchant's endorsement record in isolated merchant_endorsements table.
     */
    public function endorse(Request $request, $id): JsonResponse
    {
        $request->validate([
            'is_endorsed' => 'required|boolean',
            'endorsement_status' => 'required|string|in:PENDING,ENDORSED,SUSPENDED,EXPIRED,REJECTED',
            'endorsement_tier' => 'nullable|integer|min:1|max:5',
            'endorsement_expiry' => 'nullable|date',
            'endorsement_notes' => 'nullable|string|max:1000',
        ]);

        $tenant = Tenant::findOrFail($id);

        $endorsement = MerchantEndorsement::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'is_endorsed' => $request->input('is_endorsed'),
                'status' => strtoupper($request->input('endorsement_status')),
                'tier' => $request->input('endorsement_tier', 3),
                'expires_at' => $request->input('endorsement_expiry'),
                'notes' => $request->input('endorsement_notes'),
                'endorsed_at' => $request->input('is_endorsed') ? now() : null,
                'endorsed_by' => auth()->id() ?: 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Merchant endorsement successfully updated to '{$endorsement->status}'.",
            'data' => [
                'tenant_id' => $tenant->id,
                'company_name' => $tenant->company_name ?: $tenant->name,
                'endorsement' => $endorsement,
            ],
        ]);
    }

    /**
     * Test and inspect a merchant's eligibility against sample route parameters.
     */
    public function eligibilityCheck(Request $request, $id): JsonResponse
    {
        $request->validate([
            'origin_country_id' => 'required|integer',
            'destination_country_id' => 'required|integer',
            'mode' => 'nullable|string|in:air,ocean',
            'weight_kg' => 'nullable|numeric|min:0.1',
        ]);

        $tenant = Tenant::with(['endorsement', 'capabilities'])->findOrFail($id);

        $evaluation = $this->eligibilityService->evaluateSingleTenant(
            $tenant,
            (int)$request->input('origin_country_id'),
            (int)$request->input('destination_country_id'),
            strtolower($request->input('mode', 'air')),
            (float)$request->input('weight_kg', 1.0)
        );

        return response()->json([
            'success' => true,
            'merchant_id' => $tenant->id,
            'company_name' => $tenant->company_name ?: $tenant->name,
            'evaluation' => $evaluation,
        ]);
    }
}
