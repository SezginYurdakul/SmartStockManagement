<?php

namespace App\Enums;

/**
 * MRP Recommendation Status Enum
 */
enum MrpRecommendationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ACTIONED = 'actioned';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ACTIONED => 'Actioned',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::APPROVED => 'blue',
            self::REJECTED => 'red',
            self::ACTIONED => 'green',
            self::EXPIRED => 'gray',
        };
    }

    public function canAction(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED]);
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canReject(): bool
    {
        return $this === self::PENDING;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::ACTIONED, self::REJECTED, self::EXPIRED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
