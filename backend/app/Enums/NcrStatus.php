<?php

namespace App\Enums;

/**
 * NCR (Non-Conformance Report) Status Enum
 *
 * Tracks the workflow state of an NCR.
 * UI labels are stored in the lookup_values table.
 */
enum NcrStatus: string
{
    case OPEN = 'open';
    case UNDER_REVIEW = 'under_review';
    case PENDING_DISPOSITION = 'pending_disposition';
    case DISPOSITION_APPROVED = 'disposition_approved';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    /**
     * Check if NCR is still active (not closed/cancelled)
     */
    public function isActive(): bool
    {
        return !in_array($this, [self::CLOSED, self::CANCELLED]);
    }

    /**
     * Check if NCR can be edited
     */
    public function canEdit(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::UNDER_REVIEW,
            self::PENDING_DISPOSITION,
        ]);
    }

    /**
     * Check if NCR can transition to disposition
     */
    public function canDisposition(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::UNDER_REVIEW,
            self::PENDING_DISPOSITION,
        ]);
    }

    /**
     * Check if NCR can be closed
     */
    public function canClose(): bool
    {
        return in_array($this, [
            self::DISPOSITION_APPROVED,
            self::IN_PROGRESS,
        ]);
    }

    /**
     * Get next allowed statuses
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::UNDER_REVIEW, self::CANCELLED],
            self::UNDER_REVIEW => [self::PENDING_DISPOSITION, self::CANCELLED],
            self::PENDING_DISPOSITION => [self::DISPOSITION_APPROVED, self::CANCELLED],
            self::DISPOSITION_APPROVED => [self::IN_PROGRESS, self::CLOSED],
            self::IN_PROGRESS => [self::CLOSED],
            self::CLOSED, self::CANCELLED => [],
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get default fallback label
     */
    public function fallbackLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::UNDER_REVIEW => 'Under Review',
            self::PENDING_DISPOSITION => 'Pending Disposition',
            self::DISPOSITION_APPROVED => 'Disposition Approved',
            self::IN_PROGRESS => 'In Progress',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
