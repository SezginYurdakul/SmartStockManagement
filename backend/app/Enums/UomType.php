<?php

namespace App\Enums;

/**
 * Unit of Measure Type Enum
 *
 * Categorizes units of measure by their physical dimension.
 * Covers all common manufacturing sectors:
 * - Discrete Manufacturing (machinery, electronics, automotive)
 * - Process Manufacturing (chemicals, food, pharmaceuticals)
 * - Energy & Utilities
 *
 * New types can be added without database migration.
 */
enum UomType: string
{
    // Basic Physical Quantities
    case WEIGHT = 'weight';
    case VOLUME = 'volume';
    case LENGTH = 'length';
    case AREA = 'area';
    case QUANTITY = 'quantity';
    case TIME = 'time';

    // Mechanical
    case POWER = 'power';
    case SPEED = 'speed';
    case FLOW = 'flow';
    case PRESSURE = 'pressure';
    case FORCE = 'force';

    // Thermal & Energy
    case TEMPERATURE = 'temperature';
    case ENERGY = 'energy';

    // Electrical
    case ELECTRICITY = 'electricity';

    // Material Properties
    case DENSITY = 'density';
    case CONCENTRATION = 'concentration';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::WEIGHT => 'Weight',
            self::VOLUME => 'Volume',
            self::LENGTH => 'Length',
            self::AREA => 'Area',
            self::QUANTITY => 'Quantity',
            self::TIME => 'Time',
            self::POWER => 'Power',
            self::SPEED => 'Speed',
            self::FLOW => 'Flow Rate',
            self::PRESSURE => 'Pressure',
            self::FORCE => 'Force/Torque',
            self::TEMPERATURE => 'Temperature',
            self::ENERGY => 'Energy',
            self::ELECTRICITY => 'Electrical',
            self::DENSITY => 'Density',
            self::CONCENTRATION => 'Concentration',
        };
    }

    /**
     * Get icon for UI display (Heroicons names)
     */
    public function icon(): string
    {
        return match ($this) {
            self::WEIGHT => 'scale',
            self::VOLUME => 'beaker',
            self::LENGTH => 'ruler',
            self::AREA => 'square',
            self::QUANTITY => 'hashtag',
            self::TIME => 'clock',
            self::POWER => 'bolt',
            self::SPEED => 'gauge',
            self::FLOW => 'droplet',
            self::PRESSURE => 'gauge',
            self::FORCE => 'wrench',
            self::TEMPERATURE => 'thermometer',
            self::ENERGY => 'battery',
            self::ELECTRICITY => 'bolt',
            self::DENSITY => 'cube',
            self::CONCENTRATION => 'flask',
        };
    }

    /**
     * Get example units for this type
     */
    public function exampleUnits(): array
    {
        return match ($this) {
            self::WEIGHT => ['kg', 'g', 'lb', 'oz', 't', 'mg'],
            self::VOLUME => ['L', 'mL', 'gal', 'm³', 'cm³'],
            self::LENGTH => ['m', 'cm', 'mm', 'ft', 'in', 'km'],
            self::AREA => ['m²', 'ft²', 'ha', 'cm²', 'acre'],
            self::QUANTITY => ['pcs', 'box', 'set', 'pair', 'dozen', 'pack'],
            self::TIME => ['hr', 'min', 'sec', 'day', 'week', 'month'],
            self::POWER => ['hp', 'kW', 'W', 'MW', 'BTU/h'],
            self::SPEED => ['rpm', 'km/h', 'm/s', 'ft/min', 'mph'],
            self::FLOW => ['L/min', 'L/h', 'm³/h', 'gal/min', 'cfm'],
            self::PRESSURE => ['bar', 'psi', 'kPa', 'MPa', 'atm', 'mmHg'],
            self::FORCE => ['N', 'kN', 'Nm', 'lbf', 'kgf', 'ft-lb'],
            self::TEMPERATURE => ['°C', '°F', 'K'],
            self::ENERGY => ['kWh', 'J', 'kJ', 'MJ', 'cal', 'BTU'],
            self::ELECTRICITY => ['A', 'V', 'Ω', 'W', 'Ah', 'mA'],
            self::DENSITY => ['kg/m³', 'g/cm³', 'lb/ft³', 'kg/L'],
            self::CONCENTRATION => ['%', 'ppm', 'g/L', 'mol/L', 'mg/kg'],
        };
    }

    /**
     * Get sectors that commonly use this type
     */
    public function commonSectors(): array
    {
        return match ($this) {
            self::WEIGHT, self::VOLUME, self::LENGTH, self::AREA, self::QUANTITY, self::TIME
                => ['All sectors'],
            self::POWER, self::SPEED, self::FORCE
                => ['Machinery', 'Automotive', 'Manufacturing'],
            self::FLOW, self::PRESSURE
                => ['Hydraulics', 'Chemical', 'Food & Beverage', 'Pharmaceutical'],
            self::TEMPERATURE
                => ['Food & Beverage', 'Chemical', 'Pharmaceutical', 'HVAC'],
            self::ENERGY, self::ELECTRICITY
                => ['Energy', 'Electronics', 'Utilities'],
            self::DENSITY, self::CONCENTRATION
                => ['Chemical', 'Pharmaceutical', 'Food & Beverage'],
        };
    }

    /**
     * Check if this type can be converted to another
     */
    public function canConvertTo(self $target): bool
    {
        // Can only convert within same type
        return $this === $target;
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for select dropdowns
     */
    public static function options(): array
    {
        return array_map(
            fn(self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
            ],
            self::cases()
        );
    }

    /**
     * Get grouped options by category
     */
    public static function groupedOptions(): array
    {
        return [
            'Basic' => [
                self::WEIGHT,
                self::VOLUME,
                self::LENGTH,
                self::AREA,
                self::QUANTITY,
                self::TIME,
            ],
            'Mechanical' => [
                self::POWER,
                self::SPEED,
                self::FLOW,
                self::PRESSURE,
                self::FORCE,
            ],
            'Thermal & Energy' => [
                self::TEMPERATURE,
                self::ENERGY,
            ],
            'Electrical' => [
                self::ELECTRICITY,
            ],
            'Material Properties' => [
                self::DENSITY,
                self::CONCENTRATION,
            ],
        ];
    }
}
