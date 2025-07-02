<?php

namespace App\Exceptions;

use RuntimeException;

class ElementNotFoundException extends RuntimeException
{
    public function __construct(string $message = "L'élément demandé n'a pas été trouvé.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
