<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidPasswordException extends InvalidArgumentException
{
    public function __construct(string $message = "Le mot de passe fourni est invalide ou ne respecte pas les critères.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
