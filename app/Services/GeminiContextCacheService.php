<?php

namespace App\Services;

use App\Models\QualityFormVersion;
use App\Support\AiProviderErrors;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiContextCacheService
{
    /**
     * @return array{cache_id: string|null, token_count: int|null, status: string, hash: string}
     */
    public function cacheFor(QualityFormVersion $formVersion, string $staticContext, string $model, string $apiKey, string $systemInstruction = ''): array
    {
        $hash = $this->hashFor($staticContext, $model, $systemInstruction);

        if ($this->hasUsableCache($formVersion, $hash)) {
            return [
                'cache_id' => $formVersion->gemini_cache_id,
                'token_count' => $formVersion->gemini_cache_token_count,
                'status' => 'hit',
                'hash' => $hash,
            ];
        }

        $tokenCount = $this->countTokens($staticContext, $model, $apiKey);
        $minimumTokens = $this->minimumTokensForModel($model);

        if ($tokenCount === null || $minimumTokens === null || $tokenCount < $minimumTokens) {
            return [
                'cache_id' => null,
                'token_count' => $tokenCount,
                'status' => 'skipped',
                'hash' => $hash,
            ];
        }

        $lock = Cache::lock("gemini_cache_lock:{$formVersion->id}", 45);

        // block(3) waits up to 3s for the lock instead of hard sleep.
        // Returns true immediately if lock is available, or waits up to 3s.
        if ($lock->block(3)) {
            // Lock acquired — we are the cache creator for this form version.
            try {
                $formVersion->refresh();

                if ($this->hasUsableCache($formVersion, $hash)) {
                    return [
                        'cache_id' => $formVersion->gemini_cache_id,
                        'token_count' => $formVersion->gemini_cache_token_count,
                        'status' => 'hit_after_refresh',
                        'hash' => $hash,
                    ];
                }

                return $this->createCache($formVersion, $staticContext, $model, $apiKey, $hash, $tokenCount);
            } finally {
                $lock->release();
            }
        }

        // Lock timeout — another worker held it past 3s. Check if they created the cache.
        $formVersion->refresh();

        if ($this->hasUsableCache($formVersion, $hash)) {
            return [
                'cache_id' => $formVersion->gemini_cache_id,
                'token_count' => $formVersion->gemini_cache_token_count,
                'status' => 'hit_after_wait',
                'hash' => $hash,
            ];
        }

        // No cache found after waiting — try to create it ourselves.
        return $this->createCache($formVersion, $staticContext, $model, $apiKey, $hash, $tokenCount);
    }

    public function clear(QualityFormVersion $formVersion): void
    {
        $formVersion->update([
            'gemini_cache_id' => null,
            'gemini_cache_expires_at' => null,
            'gemini_cache_hash' => null,
            'gemini_cache_token_count' => null,
        ]);
    }

    public function isInvalidCacheError(int $status, string $message): bool
    {
        return $status === 400
            && str_contains(mb_strtolower($message), 'cachedcontent');
    }

    private function hasUsableCache(QualityFormVersion $formVersion, string $hash): bool
    {
        return filled($formVersion->gemini_cache_id)
            && $formVersion->gemini_cache_hash === $hash
            && $formVersion->gemini_cache_expires_at
            && $formVersion->gemini_cache_expires_at->isFuture();
    }

    private function hashFor(string $staticContext, string $model, string $systemInstruction): string
    {
        return hash('sha256', $model."\n".$systemInstruction."\n".$staticContext);
    }

    private function minimumTokensForModel(string $model): ?int
    {
        $normalized = $this->normalizeModel($model);
        $minimumTokens = config('ai.gemini_cache.minimum_tokens', []);
        $configured = is_array($minimumTokens) ? ($minimumTokens[$normalized] ?? null) : null;

        if ($configured !== null) {
            return (int) $configured;
        }

        $manual = config('ai.gemini_cache.manual_minimum_tokens');

        return $manual !== null && $manual !== ''
            ? max(1, (int) $manual)
            : null;
    }

    private function countTokens(string $staticContext, string $model, string $apiKey): ?int
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:countTokens?key={$apiKey}";

        try {
            $response = Http::timeout(30)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $staticContext],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::warning('Gemini countTokens failed; continuing without explicit cache.', [
                    'status' => $response->status(),
                    'message' => AiProviderErrors::sanitize($response->body()),
                ]);

                return null;
            }

            return (int) $response->json('totalTokens', 0);
        } catch (Throwable $exception) {
            Log::warning('Gemini countTokens exception; continuing without explicit cache.', [
                'message' => AiProviderErrors::sanitize($exception->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * @return array{cache_id: string|null, token_count: int|null, status: string, hash: string}
     */
    private function createCache(
        QualityFormVersion $formVersion,
        string $staticContext,
        string $model,
        string $apiKey,
        string $hash,
        int $tokenCount
    ): array {
        try {
            $response = Http::timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/cachedContents?key={$apiKey}", [
                'model' => "models/{$model}",
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $staticContext],
                        ],
                    ],
                ],
                'ttl' => config('ai.gemini_cache.ttl', '7200s'),
            ]);

            if ($response->failed()) {
                Log::warning('Gemini cachedContents create failed; continuing without explicit cache.', [
                    'status' => $response->status(),
                    'message' => AiProviderErrors::sanitize($response->body()),
                ]);

                return [
                    'cache_id' => null,
                    'token_count' => $tokenCount,
                    'status' => 'create_failed',
                    'hash' => $hash,
                ];
            }

            $cacheId = $response->json('name');
            $expiresAt = $response->json('expireTime');

            if (! is_string($cacheId) || $cacheId === '') {
                return [
                    'cache_id' => null,
                    'token_count' => $tokenCount,
                    'status' => 'missing_name',
                    'hash' => $hash,
                ];
            }

            $formVersion->update([
                'gemini_cache_id' => $cacheId,
                'gemini_cache_expires_at' => $expiresAt ? Carbon::parse($expiresAt) : now()->addHours(2),
                'gemini_cache_hash' => $hash,
                'gemini_cache_token_count' => $tokenCount,
            ]);

            return [
                'cache_id' => $cacheId,
                'token_count' => $tokenCount,
                'status' => 'created',
                'hash' => $hash,
            ];
        } catch (Throwable $exception) {
            Log::warning('Gemini cachedContents create exception; continuing without explicit cache.', [
                'message' => AiProviderErrors::sanitize($exception->getMessage()),
            ]);

            return [
                'cache_id' => null,
                'token_count' => $tokenCount,
                'status' => 'create_exception',
                'hash' => $hash,
            ];
        }
    }

    private function normalizeModel(string $model): string
    {
        return mb_strtolower(str_replace('models/', '', $model));
    }
}
