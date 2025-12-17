<?php

namespace App\Exceptions;

use Exception;

/**
 * Base exception for business logic errors.
 * These are expected errors that should return user-friendly messages.
 */
class BusinessException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message, int $statusCode = 422)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
