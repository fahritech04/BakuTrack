<?php

namespace App\Services;

use App\Models\ProductAlias;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductNormalizationService
{
    public function normalize(string $title, string $sourceName): array
    {
        $cacheKey = 'normalize:' . sha1($sourceName . '|' . Str::lower($title));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($title, $sourceName): array {
            $normalizedTitle = Str::of($title)->lower()->squish()->toString();

            $alias = ProductAlias::query()
                ->where('source_name', $sourceName)
                ->whereRaw('LOWER(alias_text) = ?', [$normalizedTitle])
                ->with('productMaster')
                ->first();

            if ($alias && $alias->productMaster) {
                return [
                    'product_master_id' => $alias->product_master_id,
                    'normalized_name' => $alias->productMaster->normalized_name,
                    'base_unit' => $alias->productMaster->base_unit,
                    'confidence' => (float) $alias->confidence,
                    'source' => 'alias',
                ];
            }

            $keywordMatch = ProductMaster::query()->get()->first(function (ProductMaster $product) use ($normalizedTitle): bool {
                $needle = Str::of($product->normalized_name)->lower()->replace('_', ' ')->toString();

                return str_contains($normalizedTitle, $needle) || str_contains($needle, $normalizedTitle);
            });

            if ($keywordMatch) {
                return [
                    'product_master_id' => $keywordMatch->id,
                    'normalized_name' => $keywordMatch->normalized_name,
                    'base_unit' => $keywordMatch->base_unit,
                    'confidence' => 0.75,
                    'source' => 'keyword',
                ];
            }

            return $this->normalizeWithLlm($title) ?? [
                'product_master_id' => null,
                'normalized_name' => Str::slug($title, '_'),
                'base_unit' => 'unit',
                'confidence' => 0.4,
                'source' => 'fallback',
            ];
        });
    }

    private function normalizeWithLlm(string $title): ?array
    {
        $decoded = $this->normalizeWithOpenAi($title) ?? $this->normalizeWithOllama($title);
        if (! is_array($decoded)) {
            return null;
        }

        return $this->mapLlmPayloadToNormalizedResult($decoded, $title);
    }

    private function normalizeWithOpenAi(string $title): ?array
    {
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return null;
        }

        $baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
        $model = config('services.openai.model', 'gpt-4o-mini');

        $response = Http::baseUrl((string) $baseUrl)
            ->timeout(20)
            ->withToken($apiKey)
            ->post('/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Normalize Indonesian raw material product names into JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Title: {$title}. Return JSON keys: normalized_name, base_unit, confidence.",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeWithOllama(string $title): ?array
    {
        $baseUrl = (string) config('services.ollama.base_url', 'http://127.0.0.1:11434');
        $model = (string) config('services.ollama.model', 'qwen2.5:1.5b');
        $enabled = (bool) config('services.ollama.enabled', true);

        if (! $enabled) {
            return null;
        }

        $prompt = <<<PROMPT
Normalize this Indonesian product title into compact JSON:
title: "{$title}"

Return JSON object with keys:
- normalized_name (string, short)
- base_unit (one of: kg, l, unit)
- confidence (0.0 to 1.0)
PROMPT;

        $response = Http::baseUrl($baseUrl)
            ->timeout(30)
            ->post('/api/generate', [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0,
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $content = data_get($response->json(), 'response');
        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function mapLlmPayloadToNormalizedResult(array $decoded, string $title): array
    {
        $baseUnit = Str::of((string) ($decoded['base_unit'] ?? 'unit'))->lower()->toString();
        if (! in_array($baseUnit, ['kg', 'l', 'unit'], true)) {
            $baseUnit = 'unit';
        }

        $confidence = (float) ($decoded['confidence'] ?? 0.65);
        if ($confidence < 0) {
            $confidence = 0;
        }
        if ($confidence > 1) {
            $confidence = 1;
        }

        return [
            'product_master_id' => null,
            'normalized_name' => Str::slug((string) ($decoded['normalized_name'] ?? $title), '_'),
            'base_unit' => $baseUnit,
            'confidence' => $confidence,
            'source' => 'llm',
        ];
    }
}
