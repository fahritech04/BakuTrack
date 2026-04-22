<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $alerts = Alert::query()
            ->where('tenant_id', $this->tenantIdFrom($request))
            ->with('watchlist:id,custom_product_name')
            ->orderByDesc('triggered_at')
            ->paginate(50);

        return response()->json($alerts);
    }

    public function ack(Request $request, Alert $alert): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        abort_if($alert->tenant_id !== $this->tenantIdFrom($request), 404);

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'data' => $alert,
        ]);
    }
}
