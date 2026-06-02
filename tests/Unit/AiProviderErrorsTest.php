<?php

namespace Tests\Unit;

use App\Exceptions\PermanentAiProviderException;
use App\Exceptions\TransientAiProviderException;
use App\Support\AiProviderErrors;
use PHPUnit\Framework\TestCase;

class AiProviderErrorsTest extends TestCase
{
    public function test_rate_limit_errors_are_transient(): void
    {
        $exception = AiProviderErrors::exceptionFor('gemini', 429, 'RESOURCE_EXHAUSTED: quota exceeded');

        $this->assertInstanceOf(TransientAiProviderException::class, $exception);
    }

    public function test_server_errors_are_transient(): void
    {
        $exception = AiProviderErrors::exceptionFor('openai', 503, 'temporarily unavailable');

        $this->assertInstanceOf(TransientAiProviderException::class, $exception);
    }

    public function test_billing_permission_errors_are_permanent(): void
    {
        $exception = AiProviderErrors::exceptionFor('gemini', 403, 'Lightning dunning decision is deny');

        $this->assertInstanceOf(PermanentAiProviderException::class, $exception);
    }

    public function test_api_keys_are_sanitized(): void
    {
        $message = AiProviderErrors::sanitize('request failed ?key=AIzaSyB123456789012345678901234567890 and sk-test12345678901234567890');

        $this->assertStringNotContainsString('AIzaSyB123456789012345678901234567890', $message);
        $this->assertStringNotContainsString('sk-test12345678901234567890', $message);
    }
}
