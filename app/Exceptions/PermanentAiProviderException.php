<?php

namespace App\Exceptions;

use RuntimeException;

class PermanentAiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $status = null
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
