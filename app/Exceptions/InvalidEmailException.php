<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidEmailException extends InvalidArgumentException
{
    public function __construct(string $message = "L'adresse email n'est pas valide ou est déjà utilisée.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
