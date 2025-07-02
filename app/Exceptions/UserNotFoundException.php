<?php

namespace App\Exceptions;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public function __construct(string $message = "L'utilisateur n'a pas été trouvé.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
