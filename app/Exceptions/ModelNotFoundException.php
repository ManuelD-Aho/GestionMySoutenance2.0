<?php

namespace App\Exceptions;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    public function __construct(string $message = "Le modèle demandé n'a pas été trouvé.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
