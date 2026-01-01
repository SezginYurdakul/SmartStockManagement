<?php

namespace App\Enums;

/**
 * Purchase Order Status Enum
 *
 * Manages PO lifecycle states and transitions.
 */
enum PoStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case SENT = 'sent';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
    case CLOSED = 'closed';

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_APPROVAL, self::CANCELLED],
            self::PENDING_APPROVAL => [self::APPROVED, self::DRAFT, self::CANCELLED],
            self::APPROVED => [self::SENT, self::CANCELLED],
            self::SENT => [self::PARTIALLY_RECEIVED, self::RECEIVED, self::CANCELLED],
            self::PARTIALLY_RECEIVED => [self::RECEIVED, self::CANCELLED],
            self::RECEIVED => [self::CLOSED],
            self::CANCELLED => [],
            self::CLOSED => [],
        };
    }

    /**
     * Check if PO can be edited
     */
    public function canEdit(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING_APPROVAL => true,
            default => false,
        };
    }

    /**
     * Check if PO can be cancelled
     */
    public function canCancel(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::SENT, self::PARTIALLY_RECEIVED => true,
            default => false,
        };
    }

    /**
     * Check if PO can receive goods
     */
    public function canReceive(): bool
    {
        return match ($this) {
            self::SENT, self::PARTIALLY_RECEIVED => true,
            default => false,
        };
    }

    /**
     * Check if PO requires approval
     */
    public function requiresApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    /**
     * Check if PO is in final state
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::CANCELLED, self::CLOSED => true,
            default => false,
        };
    }

    /**
     * Check if PO is active (not cancelled/closed)
     */
    public function isActive(): bool
    {
        return !$this->isFinal();
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get fallback label
     */
    public function fallbackLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::SENT => 'Sent to Supplier',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Received',
            self::CANCELLED => 'Cancelled',
            self::CLOSED => 'Closed',
        };
    }
}
