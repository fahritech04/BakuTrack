<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\PriceObservation;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use ResolvesTenant;

    public function summary(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $tenantId = $this->tenantIdFrom($request);
        $cacheKey = "tenant:{$tenantId}:dashboard:summary";

        $summary = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($tenantId): array {
            $todayStart = now()->startOfDay();
            $latestObservationIds = PriceObservation::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('watchlist_id')
                ->selectRaw('MAX(id) as id')
                ->groupBy('watchlist_id')
                ->pluck('id');

            $latestObservations = PriceObservation::query()
                ->whereIn('id', $latestObservationIds)
                ->with('watchlist:id,custom_product_name')
                ->orderByDesc('observed_at')
                ->limit(5)
                ->get([
                    'id',
                    'watchlist_id',
                    'listing_title',
                    'price_per_base_unit',
                    'base_unit',
                    'is_anomaly',
                    'observed_at',
                ]);

            return [
                'watchlists_total' => Watchlist::query()->where('tenant_id', $tenantId)->count(),
                'watchlists_active' => Watchlist::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'alerts_open' => Alert::query()->where('tenant_id', $tenantId)->where('status', 'open')->count(),
                'alerts_today' => Alert::query()->where('tenant_id', $tenantId)->where('triggered_at', '>=', $todayStart)->count(),
                'latest_observations' => $latestObservations
                    ->map(fn (PriceObservation $observation): array => [
                        'id' => (int) $observation->id,
                        'watchlist_id' => $observation->watchlist_id,
                        'watchlist_name' => $observation->watchlist?->custom_product_name,
                        'listing_title' => $this->sanitizeListingTitle((string) $observation->listing_title),
                        'price_per_base_unit' => (string) $observation->price_per_base_unit,
                        'base_unit' => (string) $observation->base_unit,
                        'is_anomaly' => (bool) $observation->is_anomaly,
                        'observed_at' => $observation->observed_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ];
        });

        return response()->json([
            'data' => $summary,
        ]);
    }

    private function sanitizeListingTitle(string $title): string
    {
        $cleaned = (string) preg_replace('/\s+/', ' ', trim($title));
        $cleaned = (string) preg_replace('/^\d+\s*(unit|box|dus|karton|pack)\s*/i', '', $cleaned);

        $parts = preg_split('/Rp\s*[\d\.,]+/i', $cleaned, 2);
        $cleaned = trim((string) ($parts[0] ?? $cleaned));

        return mb_strlen($cleaned) > 140
            ? mb_substr($cleaned, 0, 137) . '...'
            : $cleaned;
    }
}
