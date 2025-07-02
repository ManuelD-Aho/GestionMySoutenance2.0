<?php

namespace App\Exceptions;

use RuntimeException;

class EmailSendingException extends RuntimeException
{
    public function __construct(string $message = "Une erreur s'est produite lors de l'envoi de l'email.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
