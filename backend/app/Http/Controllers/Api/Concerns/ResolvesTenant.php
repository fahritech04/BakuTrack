<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ResolvesTenant
{
    protected function tenantIdFrom(Request $request): ?int
    {
        $tenantId = $request->user()?->tenant_id;

        return is_int($tenantId) ? $tenantId : null;
    }

    protected function tenantGuard(Request $request): ?JsonResponse
    {
        if (! $this->tenantIdFrom($request)) {
            return response()->json([
                'message' => 'Tenant context not found for this user.',
            ], 403);
        }

        return null;
    }
}
