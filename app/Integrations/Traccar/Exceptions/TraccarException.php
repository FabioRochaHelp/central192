<?php

declare(strict_types=1);

namespace App\Integrations\Traccar\Exceptions;

use RuntimeException;

final class TraccarException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        ?\Throwable $previous = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function isTimeout(): bool
    {
        return $this->statusCode === 408 || $this->statusCode === 504;
    }
}
