<?php

namespace App\Exceptions;

class InvalidAccountStateException extends AuthenticationException
{
    public function __construct(string $message = "Le compte n'est pas dans un état valide pour cette opération (ex: non activé, email non validé).", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
