<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ResolvesTenant;

    public function show(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $this->tenantIdFrom($request))
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'data' => $subscription,
        ]);
    }
}
