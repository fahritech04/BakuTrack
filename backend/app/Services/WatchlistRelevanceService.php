<?php

namespace App\Services;

use App\Models\Watchlist;
use Illuminate\Support\Str;

class WatchlistRelevanceService
{
    private const CONTEXT_BLOCK_TOKENS = [
        'holder',
        'pegangan',
        'wadah',
        'tempat',
        'rak',
        'organizer',
        'dispenser',
        'aspirator',
        'hidung',
        'pembersih',
        'bayi',
        'baby',
        'anak',
        'training',
        'sparepart',
        'aksesoris',
        'accessories',
    ];

    public function matchesWatchlist(?Watchlist $watchlist, string $listingTitle): bool
    {
        $watchlistName = trim((string) ($watchlist?->custom_product_name ?? ''));
        if ($watchlistName === '') {
            return true;
        }

        $watchTokens = $this->tokenize($watchlistName);
        $titleTokens = $this->tokenize($listingTitle);
        if ($watchTokens === [] || $titleTokens === []) {
            return true;
        }

        $titleTokenSet = array_fill_keys($titleTokens, true);
        $matched = 0;
        foreach ($watchTokens as $token) {
            if (isset($titleTokenSet[$token])) {
                $matched++;
            }
        }

        $watchTokenCount = count($watchTokens);
        if ($watchTokenCount <= 1) {
            if ($matched < 1) {
                return false;
            }
            $singleToken = $watchTokens[0] ?? null;
            if (is_string($singleToken) && ! $this->isSingleTokenProminent($singleToken, $listingTitle)) {
                return false;
            }
        } elseif ($watchTokenCount === 2) {
            if ($matched < 2) {
                return false;
            }
        } else {
            $required = max(2, (int) ceil($watchTokenCount * 0.6));
            if ($matched < $required) {
                return false;
            }
        }

        $longestToken = collect($watchTokens)->sortByDesc(fn (string $token) => strlen($token))->first();
        if (! is_string($longestToken) || $longestToken === '') {
            return ! $this->hasContextConflict($watchTokens, $titleTokens);
        }

        $hasAnchor = preg_match('/\b' . preg_quote($longestToken, '/') . '\b/u', Str::lower($listingTitle)) === 1;
        if (! $hasAnchor) {
            return false;
        }

        return ! $this->hasContextConflict($watchTokens, $titleTokens);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $normalized = Str::lower($value);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? '';

        if ($normalized === '') {
            return [];
        }

        $tokens = explode(' ', $normalized);
        $tokens = array_values(array_filter($tokens, fn (string $token) => strlen($token) >= 3));

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<int, string> $watchTokens
     * @param array<int, string> $titleTokens
     */
    private function hasContextConflict(array $watchTokens, array $titleTokens): bool
    {
        $watchSet = array_fill_keys($watchTokens, true);
        foreach (self::CONTEXT_BLOCK_TOKENS as $token) {
            if (in_array($token, $titleTokens, true) && ! isset($watchSet[$token])) {
                return true;
            }
        }

        return false;
    }

    private function isSingleTokenProminent(string $token, string $listingTitle): bool
    {
        $normalizedTitle = Str::lower($listingTitle);
        $position = mb_strpos($normalizedTitle, Str::lower($token));
        if ($position === false) {
            return false;
        }

        $maxPosition = (int) floor(mb_strlen($normalizedTitle) * 0.55);
        return $position <= $maxPosition;
    }
}

