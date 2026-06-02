<?php

namespace App\Support;

use App\Exceptions\PermanentAiProviderException;
use App\Exceptions\TransientAiProviderException;
use RuntimeException;

class AiProviderErrors
{
    public static function exceptionFor(string $provider, ?int $status, string $message): RuntimeException
    {
        $message = self::sanitize($message);

        if (self::isTransient($status, $message)) {
            return new TransientAiProviderException($message, $provider, $status);
        }

        return new PermanentAiProviderException($message, $provider, $status);
    }

    public static function isTransient(?int $status, string $message): bool
    {
        if ($status === 408 || $status === 409 || $status === 425 || $status === 429) {
            return true;
        }

        if ($status !== null && $status >= 500) {
            return true;
        }

        $message = mb_strtolower($message);

        foreach ([
            'rate limit',
            'resource_exhausted',
            'quota exceeded',
            'temporarily',
            'try again',
            'overloaded',
            'unavailable',
            'deadline',
            'connection',
            'could not connect',
            'timeout',
            'timed out',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function sanitize(string $message): string
    {
        $message = preg_replace('/([?&]key=)[^&\s"]+/i', '$1[redacted]', $message) ?? $message;
        $message = preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '[redacted-api-key]', $message) ?? $message;
        $message = preg_replace('/sk-[A-Za-z0-9_\-]{20,}/', '[redacted-api-key]', $message) ?? $message;

        return trim($message);
    }
}
