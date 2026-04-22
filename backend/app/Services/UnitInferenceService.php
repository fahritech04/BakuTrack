<?php

namespace App\Services;

use App\Models\Watchlist;
use Illuminate\Support\Str;

class UnitInferenceService
{
    public function inferWatchlistBaseUnit(?string $customProductName): string
    {
        return $this->inferFromText($customProductName) ?? 'unit';
    }

    public function resolveObservationBaseUnit(
        ?Watchlist $watchlist,
        string $listingTitle,
        string $sourceName,
        ?string $normalizedBaseUnit
    ): string {
        if ($sourceName === 'pihps') {
            return 'kg';
        }

        $watchlistUnit = strtolower((string) ($watchlist?->base_unit ?? ''));
        $combinedText = trim(((string) ($watchlist?->custom_product_name ?? '')) . ' ' . $listingTitle);

        $unitFromText = $this->inferFromText($combinedText);
        if ($unitFromText !== null) {
            return $unitFromText;
        }

        if ($watchlistUnit !== '') {
            return $watchlistUnit;
        }

        $normalized = strtolower((string) $normalizedBaseUnit);
        if ($normalized !== '' && in_array($normalized, ['kg', 'l', 'unit'], true)) {
            return $normalized;
        }

        return 'unit';
    }

    private function inferFromText(?string $text): ?string
    {
        $normalized = Str::lower(trim((string) $text));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\b(?:kg|kilogram|gr|gram|g)\b/i', $normalized) === 1) {
            return 'kg';
        }

        if (preg_match('/\b(?:ml|ltr|liter|l)\b/i', $normalized) === 1) {
            return 'l';
        }

        if (preg_match('/\b(?:pcs|pc|pack|box|dus|karton|unit|butir|buah|oz)\b/i', $normalized) === 1) {
            return 'unit';
        }

        return null;
    }
}

