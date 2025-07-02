<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidTokenException extends InvalidArgumentException
{
    public function __construct(string $message = "Le token fourni est invalide.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
