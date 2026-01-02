<?php

namespace App\Enums;

/**
 * Sales Order Status Enum
 *
 * Manages SO lifecycle states and transitions.
 */
enum SalesOrderStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case PARTIALLY_SHIPPED = 'partially_shipped';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_APPROVAL, self::CANCELLED],
            self::PENDING_APPROVAL => [self::APPROVED, self::REJECTED, self::DRAFT, self::CANCELLED],
            self::APPROVED => [self::CONFIRMED, self::CANCELLED],
            self::REJECTED => [self::DRAFT], // Can be revised and resubmitted
            self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::PARTIALLY_SHIPPED, self::SHIPPED, self::CANCELLED],
            self::PARTIALLY_SHIPPED => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to target status is allowed
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if SO can be edited
     */
    public function canEdit(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING_APPROVAL, self::REJECTED => true,
            default => false,
        };
    }

    /**
     * Check if SO can be cancelled
     */
    public function canCancel(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::CONFIRMED, self::PROCESSING, self::PARTIALLY_SHIPPED => true,
            default => false,
        };
    }

    /**
     * Check if SO can be shipped
     */
    public function canShip(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::PROCESSING, self::PARTIALLY_SHIPPED => true,
            default => false,
        };
    }

    /**
     * Check if SO requires approval
     */
    public function requiresApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }

    /**
     * Check if SO is in final state
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::CANCELLED, self::DELIVERED => true,
            default => false,
        };
    }

    /**
     * Check if SO is active
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
     * Get label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::PARTIALLY_SHIPPED => 'Partially Shipped',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }
}
