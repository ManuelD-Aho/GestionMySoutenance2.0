<?php

namespace App\Exceptions;

class AccountBlockedException extends AuthenticationException
{
    public function __construct(string $message = "Le compte est bloqué.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
