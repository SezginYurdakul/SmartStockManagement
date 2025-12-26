<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class QualityHoldException extends Exception
{
    protected array $details;

    public function __construct(string $message, array $details = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'quality_hold_error',
            'details' => $this->details,
        ], 422);
    }
}
