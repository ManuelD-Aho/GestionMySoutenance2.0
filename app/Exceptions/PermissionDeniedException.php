<?php

namespace App\Exceptions;

use RuntimeException;

class PermissionDeniedException extends RuntimeException
{
    public function __construct(string $message = "Permission refusée.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
