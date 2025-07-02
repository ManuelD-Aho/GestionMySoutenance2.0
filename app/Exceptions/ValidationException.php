<?php

namespace App\Exceptions;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    protected $errors;

    public function __construct(string $message = "Erreur de validation des données.", array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
