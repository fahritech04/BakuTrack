<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:80'],
            'event_type' => ['required', 'string', 'max:120'],
            'external_event_id' => ['required', 'string', 'max:160'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'payload' => ['required', 'array'],
        ]);

        $event = BillingEvent::query()->updateOrCreate(
            ['external_event_id' => $validated['external_event_id']],
            [
                'tenant_id' => $validated['tenant_id'] ?? null,
                'provider' => $validated['provider'],
                'event_type' => $validated['event_type'],
                'payload' => $validated['payload'],
                'processed_at' => now(),
            ]
        );

        return response()->json([
            'data' => $event,
        ]);
    }
}
