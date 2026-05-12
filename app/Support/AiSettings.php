<?php

namespace App\Support;

use App\Models\Setting;

class AiSettings
{
    public const PROVIDERS = ['simulated', 'openai', 'gemini', 'claude'];

    public const DEFAULTS = [
        'provider' => 'simulated',
        'openai_model' => 'gpt-4o-mini',
        'openai_temperature' => 0.0,
        'openai_max_tokens' => 4000,
        'gemini_model' => 'gemini-2.5-flash',
        'gemini_temperature' => 0.0,
        'claude_model' => 'claude-3-haiku-20240307',
        'claude_temperature' => 0.0,
        'claude_max_tokens' => 4000,
        'simulated_compliance_rate' => 75,
    ];

    public static function provider(): string
    {
        $provider = Setting::get('ai.provider', self::DEFAULTS['provider']);

        return in_array($provider, self::PROVIDERS, true)
            ? $provider
            : self::DEFAULTS['provider'];
    }

    public static function get(string $key): mixed
    {
        return Setting::get("ai.{$key}", self::DEFAULTS[$key] ?? null);
    }

    public static function apiKey(string $provider): string
    {
        if (! in_array($provider, ['openai', 'gemini', 'claude'], true)) {
            return '';
        }

        return (string) (Setting::get("ai.{$provider}_api_key", '') ?? '');
    }

    public static function isConfigured(string $provider): bool
    {
        return $provider === 'simulated' || self::apiKey($provider) !== '';
    }

    public static function providerConfig(?string $provider = null): array
    {
        return match ($provider ?? self::provider()) {
            'openai' => [
                'api_key' => self::apiKey('openai'),
                'model' => (string) self::get('openai_model'),
                'temperature' => (float) self::get('openai_temperature'),
                'max_tokens' => (int) self::get('openai_max_tokens'),
            ],
            'gemini' => [
                'api_key' => self::apiKey('gemini'),
                'model' => (string) self::get('gemini_model'),
                'temperature' => (float) self::get('gemini_temperature'),
            ],
            'claude' => [
                'api_key' => self::apiKey('claude'),
                'model' => (string) self::get('claude_model'),
                'temperature' => (float) self::get('claude_temperature'),
                'max_tokens' => (int) self::get('claude_max_tokens'),
            ],
            default => [
                'compliance_rate' => (int) self::get('simulated_compliance_rate'),
            ],
        };
    }

    public static function transcriptionConfig(): array
    {
        return [
            'api_key' => self::apiKey('gemini'),
            'model' => (string) self::get('gemini_model'),
            'temperature' => (float) self::get('gemini_temperature'),
        ];
    }
}
