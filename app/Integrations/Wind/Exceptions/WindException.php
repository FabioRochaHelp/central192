<?php

declare(strict_types=1);

namespace App\Integrations\Wind\Exceptions;

use RuntimeException;
use Throwable;

final class WindException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
