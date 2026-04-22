<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\ScrapeJob;
use App\Models\Watchlist;
use App\Services\ScrapeIngestionService;
use App\Services\TenantCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalScrapeController extends Controller
{
    public function __construct(
        private readonly ScrapeIngestionService $scrapeIngestion,
        private readonly TenantCacheService $tenantCache,
    ) {
    }

    public function dispatch(Request $request): JsonResponse
    {
        if ($response = $this->checkInternalKey($request)) {
            return $response;
        }

        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'source_name' => ['nullable', 'string', 'max:60'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        $watchlists = Watchlist::query()
            ->where('is_active', true)
            ->when(
                $validated['tenant_id'] ?? null,
                fn ($query, $tenantId) => $query->where('tenant_id', $tenantId)
            )
            ->get();

        $jobs = [];

        foreach ($watchlists as $watchlist) {
            $jobs[] = ScrapeJob::query()->create([
                'tenant_id' => $watchlist->tenant_id,
                'watchlist_id' => $watchlist->id,
                'source_name' => $validated['source_name'] ?? 'hybrid',
                'status' => 'queued',
                'scheduled_for' => $validated['scheduled_for'] ?? now(),
                'payload' => [
                    'watchlist_id' => $watchlist->id,
                    'product_hint' => $watchlist->custom_product_name,
                ],
            ]);
        }

        return response()->json([
            'data' => $jobs,
            'meta' => ['count' => count($jobs)],
        ], 201);
    }

    public function ingestResults(Request $request): JsonResponse
    {
        if ($response = $this->checkInternalKey($request)) {
            return $response;
        }

        $validated = $request->validate([
            'scrape_job_id' => ['required', 'integer', 'exists:scrape_jobs,id'],
            'source_name' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.listing_title' => ['required', 'string', 'max:255'],
            'items.*.listing_price_text' => ['required', 'string', 'max:100'],
            'items.*.listing_unit_text' => ['nullable', 'string', 'max:100'],
            'items.*.listing_url' => ['nullable', 'string', 'max:1000'],
            'items.*.supplier_name' => ['nullable', 'string', 'max:160'],
            'items.*.supplier_external_id' => ['nullable', 'string', 'max:160'],
            'items.*.raw_payload' => ['nullable', 'array'],
            'items.*.scraped_at' => ['nullable', 'date'],
        ]);

        $job = ScrapeJob::query()->with('watchlist')->findOrFail($validated['scrape_job_id']);
        $resolvedSource = (string) ($validated['source_name'] ?? $job->source_name);
        $job->update(['status' => 'running', 'started_at' => $job->started_at ?? now()]);

        $ingestResult = $this->scrapeIngestion->ingest(
            job: $job,
            resolvedSource: $resolvedSource,
            items: $validated['items'],
        );

        $job->update([
            'status' => 'success',
            'finished_at' => now(),
            'attempts' => $job->attempts + 1,
        ]);

        $this->tenantCache->clearDashboardAndLatestPrices(
            tenantId: (int) $job->tenant_id,
            watchlistId: $job->watchlist_id ? (int) $job->watchlist_id : null,
        );

        return response()->json([
            'data' => [
                'scrape_job_id' => $job->id,
                'observations_saved' => $ingestResult['observations_saved'],
                'alerts_created' => $ingestResult['alerts_created'],
            ],
        ], 201);
    }

    public function updateScrapeJobStatus(Request $request): JsonResponse
    {
        if ($response = $this->checkInternalKey($request)) {
            return $response;
        }

        $validated = $request->validate([
            'scrape_job_id' => ['required', 'integer', 'exists:scrape_jobs,id'],
            'status' => ['required', 'in:failed,running,queued,success'],
            'error_message' => ['nullable', 'string', 'max:500'],
        ]);

        $job = ScrapeJob::query()->findOrFail($validated['scrape_job_id']);
        $nextStatus = $validated['status'];
        $errorMessage = $validated['error_message'] ?? null;

        $payload = is_array($job->payload) ? $job->payload : [];
        if ($errorMessage) {
            $payload['last_error'] = $errorMessage;
        }
        $isTerminalStatus = in_array($nextStatus, ['failed', 'success'], true);
        $isRunningStatus = $nextStatus === 'running';

        $job->update([
            'status' => $nextStatus,
            'started_at' => $isRunningStatus ? ($job->started_at ?? now()) : $job->started_at,
            'finished_at' => $isTerminalStatus ? now() : null,
            'attempts' => $isTerminalStatus ? ($job->attempts + 1) : $job->attempts,
            'payload' => $payload,
        ]);

        return response()->json([
            'data' => [
                'scrape_job_id' => $job->id,
                'status' => $job->status,
            ],
        ]);
    }

    public function whatsappWebhook(Request $request): JsonResponse
    {
        $expectedSecret = config('bakutrack.whatsapp_webhook_secret');

        if ($expectedSecret && $request->header('X-Webhook-Secret') !== $expectedSecret) {
            return response()->json(['message' => 'Unauthorized webhook'], 401);
        }

        return response()->json([
            'message' => 'Webhook received.',
            'data' => $request->all(),
        ]);
    }

    public function pendingNotifications(Request $request): JsonResponse
    {
        if ($response = $this->checkInternalKey($request)) {
            return $response;
        }

        $limit = max(1, min(100, $request->integer('limit', 20)));

        $notifications = NotificationLog::query()
            ->where('status', 'pending')
            ->with(['alert:id,message,alert_type,triggered_at', 'tenant:id,whatsapp_phone,name'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $notifications,
        ]);
    }

    public function updateNotificationStatus(Request $request, NotificationLog $notification): JsonResponse
    {
        if ($response = $this->checkInternalKey($request)) {
            return $response;
        }

        $validated = $request->validate([
            'status' => ['required', 'in:sent,failed'],
            'provider_message_id' => ['nullable', 'string', 'max:160'],
            'response_payload' => ['nullable', 'array'],
        ]);

        $notification->update([
            'status' => $validated['status'],
            'provider_message_id' => $validated['provider_message_id'] ?? $notification->provider_message_id,
            'response_payload' => $validated['response_payload'] ?? $notification->response_payload,
            'sent_at' => $validated['status'] === 'sent' ? now() : $notification->sent_at,
            'failed_at' => $validated['status'] === 'failed' ? now() : $notification->failed_at,
        ]);

        if ($validated['status'] === 'sent') {
            $notification->alert?->update(['status' => 'sent']);
        }

        return response()->json([
            'data' => $notification,
        ]);
    }

    private function checkInternalKey(Request $request): ?JsonResponse
    {
        $configuredKey = config('bakutrack.internal_key');

        if (! $configuredKey) {
            return response()->json([
                'message' => 'BAKUTRACK_INTERNAL_KEY is not configured.',
            ], 500);
        }

        if ($request->header('X-Internal-Key') !== $configuredKey) {
            return response()->json([
                'message' => 'Unauthorized internal access.',
            ], 401);
        }

        return null;
    }
}
