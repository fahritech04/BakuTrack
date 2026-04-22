<?php

namespace App\Services;

use App\Models\ScrapeJob;
use Illuminate\Support\Carbon;

class ScrapeIngestionService
{
    public function __construct(
        private readonly PriceParserService $priceParser,
        private readonly ProductNormalizationService $normalizer,
        private readonly AnomalyDetectionService $anomalyDetector,
        private readonly AlertEvaluationService $alertEvaluator,
        private readonly UnitInferenceService $unitInference,
        private readonly WatchlistRelevanceService $watchlistRelevance,
        private readonly ObservationUpsertService $observationUpsert,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{observations_saved:int,alerts_created:int}
     */
    public function ingest(ScrapeJob $job, string $resolvedSource, array $items): array
    {
        $alertsCreated = 0;
        $observationsSaved = 0;

        foreach ($items as $item) {
            $price = $this->priceParser->parseRupiah((string) ($item['listing_price_text'] ?? ''));
            if ($price === null) {
                continue;
            }

            $listingTitle = (string) ($item['listing_title'] ?? '');
            if (! $this->watchlistRelevance->matchesWatchlist($job->watchlist, $listingTitle)) {
                continue;
            }

            $scrapedAt = Carbon::parse($item['scraped_at'] ?? now());
            $observedDay = $scrapedAt->toDateString();

            $quantity = $this->priceParser->parseQuantity((string) ($item['listing_unit_text'] ?? ''));
            $pricePerBaseUnit = $quantity > 0 ? round($price / $quantity, 2) : $price;

            $raw = $this->observationUpsert->upsertRaw(
                job: $job,
                resolvedSource: $resolvedSource,
                item: $item,
                scrapedAt: $scrapedAt,
                observedDay: $observedDay,
            );

            $normalized = $this->normalizer->normalize($raw->listing_title, $resolvedSource);
            $supplier = $this->observationUpsert->resolveSupplier(
                tenantId: (int) $job->tenant_id,
                resolvedSource: $resolvedSource,
                item: $item,
            );

            $baseUnit = $this->unitInference->resolveObservationBaseUnit(
                watchlist: $job->watchlist,
                listingTitle: $raw->listing_title,
                sourceName: $resolvedSource,
                normalizedBaseUnit: $normalized['base_unit'] ?? null,
            );

            $anomalyResult = $this->anomalyDetector->detect(
                tenantId: (int) $job->tenant_id,
                watchlistId: $job->watchlist_id ? (int) $job->watchlist_id : null,
                currentPricePerUnit: $pricePerBaseUnit,
                baseUnit: $baseUnit,
            );

            $observationPayload = [
                'tenant_id' => $job->tenant_id,
                'watchlist_id' => $job->watchlist_id,
                'product_master_id' => $normalized['product_master_id'],
                'supplier_id' => $supplier?->id,
                'scrape_job_id' => $job->id,
                'source_name' => $resolvedSource,
                'listing_title' => $raw->listing_title,
                'price' => $price,
                'base_unit' => $baseUnit,
                'quantity' => $quantity,
                'price_per_base_unit' => $pricePerBaseUnit,
                'confidence_score' => $normalized['confidence'] ?? 0.7,
                'is_anomaly' => $anomalyResult['is_anomaly'],
                'anomaly_reason' => $anomalyResult['reason'],
                'observed_at' => $raw->scraped_at,
            ];

            $observation = $this->observationUpsert->upsertObservation(
                job: $job,
                resolvedSource: $resolvedSource,
                raw: $raw,
                observedDay: $observedDay,
                observationPayload: $observationPayload,
            );

            $alerts = $this->alertEvaluator->evaluate($observation);
            $alertsCreated += count($alerts);
            $observationsSaved++;
        }

        return [
            'observations_saved' => $observationsSaved,
            'alerts_created' => $alertsCreated,
        ];
    }
}

