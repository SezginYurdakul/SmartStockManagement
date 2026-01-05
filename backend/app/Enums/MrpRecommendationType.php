<?php

namespace App\Enums;

/**
 * MRP Recommendation Type Enum
 */
enum MrpRecommendationType: string
{
    case PURCHASE_ORDER = 'purchase_order';
    case WORK_ORDER = 'work_order';
    case TRANSFER = 'transfer';
    case RESCHEDULE_IN = 'reschedule_in';
    case RESCHEDULE_OUT = 'reschedule_out';
    case CANCEL = 'cancel';
    case EXPEDITE = 'expedite';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'Purchase Order',
            self::WORK_ORDER => 'Work Order',
            self::TRANSFER => 'Transfer',
            self::RESCHEDULE_IN => 'Reschedule In',
            self::RESCHEDULE_OUT => 'Reschedule Out',
            self::CANCEL => 'Cancel',
            self::EXPEDITE => 'Expedite',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'blue',
            self::WORK_ORDER => 'green',
            self::TRANSFER => 'purple',
            self::RESCHEDULE_IN => 'orange',
            self::RESCHEDULE_OUT => 'yellow',
            self::CANCEL => 'red',
            self::EXPEDITE => 'pink',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'shopping-cart',
            self::WORK_ORDER => 'cog',
            self::TRANSFER => 'arrows-alt',
            self::RESCHEDULE_IN => 'arrow-left',
            self::RESCHEDULE_OUT => 'arrow-right',
            self::CANCEL => 'times-circle',
            self::EXPEDITE => 'bolt',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'Create a new purchase order from supplier',
            self::WORK_ORDER => 'Create a new work order for manufacturing',
            self::TRANSFER => 'Transfer stock between warehouses',
            self::RESCHEDULE_IN => 'Move existing order date earlier',
            self::RESCHEDULE_OUT => 'Move existing order date later',
            self::CANCEL => 'Cancel existing order (no longer needed)',
            self::EXPEDITE => 'Expedite existing order (urgent)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}
