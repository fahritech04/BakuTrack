<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\PriceObservation;
use App\Models\Watchlist;
use Carbon\Carbon;

class AlertEvaluationService
{
    /**
     * @return array<int, Alert>
     */
    public function evaluate(PriceObservation $observation): array
    {
        if ($observation->is_anomaly) {
            return [];
        }

        $watchlist = $observation->watchlist;

        if (! $watchlist instanceof Watchlist) {
            return [];
        }

        $alerts = [];

        $baseline = PriceObservation::query()
            ->where('tenant_id', $observation->tenant_id)
            ->where('watchlist_id', $observation->watchlist_id)
            ->whereDate('observed_at', '<', $observation->observed_at->toDateString())
            ->where('is_anomaly', false)
            ->orderByDesc('observed_at')
            ->value('price_per_base_unit');

        if ($baseline) {
            $dropPct = (1 - ((float) $observation->price_per_base_unit / (float) $baseline)) * 100;
            if ($dropPct >= (float) $watchlist->drop_threshold_pct) {
                $alerts[] = $this->createAlert(
                    type: 'price_drop',
                    observation: $observation,
                    message: sprintf('Harga turun %.2f%% untuk %s.', $dropPct, $watchlist->custom_product_name ?? 'item watchlist'),
                    baseline: (float) $baseline,
                    threshold: (float) $watchlist->drop_threshold_pct,
                    extra: ['drop_pct' => round($dropPct, 2)]
                );
            }
        }

        $marketAverage = PriceObservation::query()
            ->where('tenant_id', $observation->tenant_id)
            ->where('watchlist_id', $observation->watchlist_id)
            ->whereDate('observed_at', $observation->observed_at->toDateString())
            ->where('is_anomaly', false)
            ->avg('price_per_base_unit');

        if ($marketAverage) {
            $discountPct = (1 - ((float) $observation->price_per_base_unit / (float) $marketAverage)) * 100;
            if ($discountPct >= (float) $watchlist->arbitrage_threshold_pct) {
                $alerts[] = $this->createAlert(
                    type: 'arbitrage',
                    observation: $observation,
                    message: sprintf('Peluang arbitrage: %.2f%% di bawah rerata pasar.', $discountPct),
                    baseline: (float) $marketAverage,
                    threshold: (float) $watchlist->arbitrage_threshold_pct,
                    extra: ['discount_pct' => round($discountPct, 2)]
                );
            }
        }

        return $alerts;
    }

    private function createAlert(
        string $type,
        PriceObservation $observation,
        string $message,
        float $baseline,
        float $threshold,
        array $extra
    ): Alert {
        $existing = Alert::query()
            ->where('tenant_id', $observation->tenant_id)
            ->where('watchlist_id', $observation->watchlist_id)
            ->where('alert_type', $type)
            ->whereDate('triggered_at', Carbon::today())
            ->where('status', 'open')
            ->first();

        if ($existing instanceof Alert) {
            return $existing;
        }

        $alert = Alert::query()->create([
            'tenant_id' => $observation->tenant_id,
            'watchlist_id' => $observation->watchlist_id,
            'price_observation_id' => $observation->id,
            'alert_type' => $type,
            'status' => 'open',
            'message' => $message,
            'trigger_value' => $observation->price_per_base_unit,
            'baseline_value' => $baseline,
            'threshold_pct' => $threshold,
            'metadata' => $extra,
            'triggered_at' => Carbon::now(),
        ]);

        NotificationLog::query()->create([
            'tenant_id' => $observation->tenant_id,
            'alert_id' => $alert->id,
            'destination' => optional($observation->tenant)->whatsapp_phone ?? '-',
            'status' => 'pending',
        ]);

        return $alert;
    }
}
