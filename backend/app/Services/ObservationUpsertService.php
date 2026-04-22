<?php

namespace App\Services;

use App\Models\PriceObservation;
use App\Models\ScrapeJob;
use App\Models\ScrapeResultRaw;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ObservationUpsertService
{
    /**
     * @param array<string, mixed> $item
     */
    public function upsertRaw(
        ScrapeJob $job,
        string $resolvedSource,
        array $item,
        Carbon $scrapedAt,
        string $observedDay
    ): ScrapeResultRaw {
        $raw = ScrapeResultRaw::query()
            ->where('tenant_id', $job->tenant_id)
            ->where('watchlist_id', $job->watchlist_id)
            ->where('source_name', $resolvedSource)
            ->whereDate('scraped_at', $observedDay)
            ->whereRaw('LOWER(listing_title) = ?', [Str::lower((string) $item['listing_title'])])
            ->first();

        $rawPayload = [
            'scrape_job_id' => $job->id,
            'listing_price_text' => (string) $item['listing_price_text'],
            'listing_unit_text' => $item['listing_unit_text'] ?? null,
            'listing_url' => $item['listing_url'] ?? null,
            'raw_payload' => $item['raw_payload'] ?? null,
            'scraped_at' => $scrapedAt,
        ];

        if ($raw instanceof ScrapeResultRaw) {
            $raw->fill($rawPayload);
            $raw->save();

            return $raw;
        }

        return ScrapeResultRaw::query()->create([
            'scrape_job_id' => $job->id,
            'tenant_id' => $job->tenant_id,
            'watchlist_id' => $job->watchlist_id,
            'source_name' => $resolvedSource,
            'listing_title' => (string) $item['listing_title'],
            ...$rawPayload,
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function resolveSupplier(int $tenantId, string $resolvedSource, array $item): ?Supplier
    {
        $supplierName = trim((string) ($item['supplier_name'] ?? ''));
        if ($supplierName === '') {
            return null;
        }

        return Supplier::query()->firstOrCreate(
            [
                'source_name' => $resolvedSource,
                'external_id' => $item['supplier_external_id'] ?? null,
                'display_name' => $supplierName,
            ],
            [
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]
        );
    }

    /**
     * @param array<string, mixed> $observationPayload
     */
    public function upsertObservation(
        ScrapeJob $job,
        string $resolvedSource,
        ScrapeResultRaw $raw,
        string $observedDay,
        array $observationPayload
    ): PriceObservation {
        $observation = PriceObservation::query()
            ->where('tenant_id', $job->tenant_id)
            ->where('watchlist_id', $job->watchlist_id)
            ->where('source_name', $resolvedSource)
            ->whereDate('observed_at', $observedDay)
            ->whereRaw('LOWER(listing_title) = ?', [Str::lower((string) $raw->listing_title)])
            ->first();

        if ($observation instanceof PriceObservation) {
            $observation->fill($observationPayload);
            $observation->save();

            return $observation;
        }

        return PriceObservation::query()->create($observationPayload);
    }
}

