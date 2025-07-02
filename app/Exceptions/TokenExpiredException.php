<?php

namespace App\Exceptions;

class TokenExpiredException extends InvalidTokenException
{
    public function __construct(string $message = "Le token a expiré.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
