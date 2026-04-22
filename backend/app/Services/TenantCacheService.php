<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TenantCacheService
{
    public function clearDashboardAndLatestPrices(int $tenantId, ?int $watchlistId = null): void
    {
        Cache::forget("tenant:{$tenantId}:dashboard:summary");
        Cache::forget("tenant:{$tenantId}:prices:latest:all");

        if ($watchlistId !== null) {
            Cache::forget("tenant:{$tenantId}:prices:latest:{$watchlistId}");
        }
    }
}

