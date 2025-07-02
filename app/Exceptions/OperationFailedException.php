<?php

namespace App\Exceptions;

use LogicException;

class OperationFailedException extends LogicException
{
    public function __construct(string $message = "L'opération demandée ne peut être effectuée.", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
