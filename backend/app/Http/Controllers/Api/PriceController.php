<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\PriceObservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PriceController extends Controller
{
    use ResolvesTenant;

    public function latest(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $tenantId = $this->tenantIdFrom($request);
        $watchlistId = $request->integer('watchlist_id');
        $cacheKey = "tenant:{$tenantId}:prices:latest:" . ($watchlistId ?: 'all');

        $results = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenantId, $watchlistId) {
            $query = PriceObservation::query()
                ->where('tenant_id', $tenantId)
                ->when($watchlistId, fn ($q) => $q->where('watchlist_id', $watchlistId))
                ->orderByDesc('observed_at')
                ->with('watchlist:id,custom_product_name')
                ->limit(100);

            return $query->get();
        });

        return response()->json([
            'data' => $results,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $validated = $request->validate([
            'watchlist_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = PriceObservation::query()
            ->where('tenant_id', $this->tenantIdFrom($request))
            ->when(
                $validated['watchlist_id'] ?? null,
                fn ($q, $watchlistId) => $q->where('watchlist_id', $watchlistId)
            )
            ->when(
                $validated['supplier_id'] ?? null,
                fn ($q, $supplierId) => $q->where('supplier_id', $supplierId)
            )
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('observed_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('observed_at', '<=', $to))
            ->orderByDesc('observed_at');

        return response()->json($query->paginate(100));
    }
}
