<?php

namespace App\Services;

class PriceParserService
{
    public function parseRupiah(string $raw): ?float
    {
        $cleaned = preg_replace('/[^0-9,\.]/', '', $raw);

        if (! is_string($cleaned) || $cleaned === '') {
            return null;
        }

        // Normalisasi berbagai format umum:
        // 12.500,50 -> 12500.50
        // 12,500.50 -> 12500.50
        // 37.500 -> 37500
        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            if (preg_match('/^\d{1,3}(,\d{3})+$/', $cleaned) === 1) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                $cleaned = str_replace(',', '.', $cleaned);
            }
        } elseif (str_contains($cleaned, '.')) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned) === 1) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        }

        $price = (float) $cleaned;

        if ($price <= 0) {
            return null;
        }

        return round($price, 2);
    }

    public function parseQuantity(?string $rawUnit): float
    {
        if (! $rawUnit) {
            return 1.0;
        }

        if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)/', $rawUnit, $matches) !== 1) {
            return 1.0;
        }

        $value = str_replace(',', '.', $matches[1]);

        return max((float) $value, 1.0);
    }
}
