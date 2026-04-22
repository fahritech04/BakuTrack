<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\PriceObservation;
use App\Models\ScrapeJob;
use App\Models\Watchlist;
use App\Services\TenantCacheService;
use App\Services\UnitInferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    use ResolvesTenant;

    public function __construct(
        private readonly UnitInferenceService $unitInference,
        private readonly TenantCacheService $tenantCache,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $watchlists = Watchlist::query()
            ->where('tenant_id', $this->tenantIdFrom($request))
            ->with('productMaster')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($watchlists);
    }

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $validated = $request->validate([
            'product_master_id' => ['nullable', 'integer', 'exists:product_masters,id'],
            'custom_product_name' => ['nullable', 'string', 'max:120'],
            'target_price' => ['nullable', 'numeric', 'min:1'],
            'drop_threshold_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'arbitrage_threshold_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $baseUnit = $validated['base_unit']
            ?? $this->unitInference->inferWatchlistBaseUnit($validated['custom_product_name'] ?? null);

        $watchlist = Watchlist::query()->create([
            'tenant_id' => $this->tenantIdFrom($request),
            'product_master_id' => $validated['product_master_id'] ?? null,
            'custom_product_name' => $validated['custom_product_name'] ?? null,
            'target_price' => $validated['target_price'] ?? null,
            'drop_threshold_pct' => $validated['drop_threshold_pct'] ?? 10,
            'arbitrage_threshold_pct' => $validated['arbitrage_threshold_pct'] ?? 15,
            'base_unit' => $baseUnit,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->tenantCache->clearDashboardAndLatestPrices((int) $watchlist->tenant_id);

        return response()->json([
            'data' => $watchlist,
        ], 201);
    }

    public function show(Request $request, Watchlist $watchlist): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        abort_if($watchlist->tenant_id !== $this->tenantIdFrom($request), 404);

        return response()->json([
            'data' => $watchlist->load('productMaster'),
        ]);
    }

    public function update(Request $request, Watchlist $watchlist): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        abort_if($watchlist->tenant_id !== $this->tenantIdFrom($request), 404);

        $validated = $request->validate([
            'target_price' => ['nullable', 'numeric', 'min:1'],
            'drop_threshold_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'arbitrage_threshold_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'custom_product_name' => ['nullable', 'string', 'max:120'],
            'base_unit' => ['nullable', 'string', 'max:20'],
        ]);

        if (! array_key_exists('base_unit', $validated) && array_key_exists('custom_product_name', $validated)) {
            $validated['base_unit'] = $this->unitInference->inferWatchlistBaseUnit($validated['custom_product_name']);
        }

        $watchlist->fill($validated);
        $watchlist->save();
        $this->tenantCache->clearDashboardAndLatestPrices((int) $watchlist->tenant_id);

        return response()->json([
            'data' => $watchlist,
        ]);
    }

    public function destroy(Request $request, Watchlist $watchlist): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        abort_if($watchlist->tenant_id !== $this->tenantIdFrom($request), 404);

        $tenantId = (int) $watchlist->tenant_id;
        $watchlistId = (int) $watchlist->id;

        $observationIds = PriceObservation::query()
            ->where('tenant_id', $tenantId)
            ->where('watchlist_id', $watchlistId)
            ->pluck('id');

        $alertIds = Alert::query()
            ->where('tenant_id', $tenantId)
            ->where('watchlist_id', $watchlistId)
            ->pluck('id');

        if ($observationIds->isNotEmpty()) {
            Alert::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('price_observation_id', $observationIds)
                ->pluck('id')
                ->each(function (int $alertId) use (&$alertIds): void {
                    $alertIds->push($alertId);
                });
        }

        $alertIds = $alertIds->unique()->values();

        if ($alertIds->isNotEmpty()) {
            NotificationLog::query()->whereIn('alert_id', $alertIds)->delete();
            Alert::query()->whereIn('id', $alertIds)->delete();
        }

        if ($observationIds->isNotEmpty()) {
            PriceObservation::query()->whereIn('id', $observationIds)->delete();
        }

        ScrapeJob::query()
            ->where('tenant_id', $tenantId)
            ->where('watchlist_id', $watchlistId)
            ->delete();

        $watchlist->delete();
        $this->tenantCache->clearDashboardAndLatestPrices($tenantId);

        return response()->json(status: 204);
    }

}
