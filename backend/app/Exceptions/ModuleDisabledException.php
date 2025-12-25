<?php

namespace App\Exceptions;

/**
 * Exception thrown when attempting to access a disabled module or feature.
 */
class ModuleDisabledException extends BusinessException
{
    protected string $module;
    protected ?string $feature;

    public function __construct(
        string $message,
        string $module,
        ?string $feature = null,
        int $statusCode = 403
    ) {
        parent::__construct($message, $statusCode);
        $this->module = $module;
        $this->feature = $feature;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * Get error details for API response
     */
    public function getErrorDetails(): array
    {
        return [
            'error' => 'module_disabled',
            'module' => $this->module,
            'feature' => $this->feature,
            'message' => $this->getMessage(),
        ];
    }
}
