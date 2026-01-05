<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * NOTE: Enum-like values (inspection_types, sampling_methods, results, statuses, etc.)
     * are defined as constants in their respective models (ReceivingInspection, NonConformanceReport, etc.)
     * This ensures consistency with database enum constraints and provides type-safety.
     *
     * Settings table should only contain truly dynamic/configurable values.
     */
    public function run(): void
    {
        $settings = [
            // ===================
            // QC Default Settings
            // ===================
            [
                'group' => 'qc',
                'key' => 'default_aql_level',
                'value' => 'II',
                'description' => 'Default AQL inspection level for new acceptance rules',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'default_sampling_method',
                'value' => 'aql',
                'description' => 'Default sampling method for new acceptance rules',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'auto_create_ncr_on_failure',
                'value' => true,
                'description' => 'Automatically create NCR when inspection fails',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'require_approval_for_use_as_is',
                'value' => true,
                'description' => 'Require manager approval for use-as-is disposition',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'quarantine_critical_ncr',
                'value' => true,
                'description' => 'Automatically quarantine stock for critical NCRs',
                'is_system' => true,
            ],

            // ===================
            // AQL Reference Tables
            // ===================
            [
                'group' => 'qc',
                'key' => 'aql_levels',
                'value' => [
                    'S-1' => 'Special Level S-1',
                    'S-2' => 'Special Level S-2',
                    'S-3' => 'Special Level S-3',
                    'S-4' => 'Special Level S-4',
                    'I' => 'General Level I',
                    'II' => 'General Level II (Standard)',
                    'III' => 'General Level III',
                ],
                'description' => 'AQL inspection levels (ANSI/ASQ Z1.4)',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'aql_values',
                'value' => [
                    '0.065' => '0.065%',
                    '0.10' => '0.10%',
                    '0.15' => '0.15%',
                    '0.25' => '0.25%',
                    '0.40' => '0.40%',
                    '0.65' => '0.65%',
                    '1.0' => '1.0%',
                    '1.5' => '1.5%',
                    '2.5' => '2.5%',
                    '4.0' => '4.0%',
                    '6.5' => '6.5%',
                ],
                'description' => 'Acceptable Quality Level percentages',
                'is_system' => true,
            ],

            // ===================
            // Notification Settings
            // ===================
            [
                'group' => 'qc',
                'key' => 'notify_on_critical_ncr',
                'value' => true,
                'description' => 'Send email notification for critical NCRs',
                'is_system' => true,
            ],
            [
                'group' => 'qc',
                'key' => 'critical_ncr_notify_emails',
                'value' => [],
                'description' => 'Email addresses to notify for critical NCRs',
                'is_system' => false,
            ],

            // ===================
            // General App Settings
            // ===================
            [
                'group' => 'app',
                'key' => 'items_per_page',
                'value' => 15,
                'description' => 'Default number of items per page in listings',
                'is_system' => true,
            ],
            [
                'group' => 'app',
                'key' => 'date_format',
                'value' => 'Y-m-d',
                'description' => 'Default date format for display',
                'is_system' => true,
            ],
            [
                'group' => 'app',
                'key' => 'datetime_format',
                'value' => 'Y-m-d H:i:s',
                'description' => 'Default datetime format for display',
                'is_system' => true,
            ],

            // ===================
            // MRP Settings
            // ===================
            [
                'group' => 'mrp',
                'key' => 'working_days',
                'value' => [1, 2, 3, 4, 5], // Monday to Friday (0=Sunday, 1=Monday, ..., 6=Saturday)
                'description' => 'Standard working days for MRP calculations. Array of day numbers: 0=Sunday, 1=Monday, ..., 6=Saturday',
                'is_system' => true, // Only admin can modify
            ],
            [
                'group' => 'mrp',
                'key' => 'default_shift',
                'value' => [
                    'name' => 'default',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'break_hours' => 1.0,
                    'working_hours' => 8.0,
                ],
                'description' => 'Default shift configuration for MRP calculations',
                'is_system' => true,
            ],
            [
                'group' => 'mrp',
                'key' => 'shifts',
                'value' => [
                    'default' => [
                        'name' => 'default',
                        'start_time' => '08:00:00',
                        'end_time' => '17:00:00',
                        'break_hours' => 1.0,
                        'working_hours' => 8.0,
                    ],
                ],
                'description' => 'Available shifts for MRP calculations. Can define multiple shifts (morning, afternoon, night, etc.)',
                'is_system' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Settings seeded successfully!');
    }
}
