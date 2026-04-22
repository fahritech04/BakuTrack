<?php

namespace App\Services;

use App\Models\PriceObservation;

class AnomalyDetectionService
{
    public function detect(
        int $tenantId,
        ?int $watchlistId,
        float $currentPricePerUnit,
        ?string $baseUnit = null
    ): array {
        if ($currentPricePerUnit <= 0) {
            return ['is_anomaly' => true, 'reason' => 'non_positive_price'];
        }

        $historyQuery = PriceObservation::query()
            ->where('tenant_id', $tenantId)
            ->where('is_anomaly', false)
            ->orderByDesc('observed_at')
            ->limit(30);

        if ($watchlistId) {
            $historyQuery->where('watchlist_id', $watchlistId);
        }
        if ($baseUnit) {
            $historyQuery->where('base_unit', $baseUnit);
        }

        $history = $historyQuery->pluck('price_per_base_unit')->map(fn ($v) => (float) $v)->all();

        if (count($history) < 5) {
            return ['is_anomaly' => false, 'reason' => null];
        }

        sort($history);
        $median = $this->median($history);

        if ($median <= 0) {
            return ['is_anomaly' => false, 'reason' => null];
        }

        if ($currentPricePerUnit < $median * 0.2 || $currentPricePerUnit > $median * 3.5) {
            return ['is_anomaly' => true, 'reason' => 'outside_guardrail'];
        }

        $deviations = array_map(fn (float $value) => abs($value - $median), $history);
        sort($deviations);
        $mad = $this->median($deviations);

        if ($mad === 0.0) {
            return ['is_anomaly' => false, 'reason' => null];
        }

        $modifiedZScore = 0.6745 * abs($currentPricePerUnit - $median) / $mad;

        if ($modifiedZScore > 6) {
            return ['is_anomaly' => true, 'reason' => 'high_modified_z_score'];
        }

        return ['is_anomaly' => false, 'reason' => null];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        $count = count($values);

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
