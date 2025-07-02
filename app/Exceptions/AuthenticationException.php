<?php

namespace App\Exceptions;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    public function __construct(string $message = "Erreur d'authentification.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
