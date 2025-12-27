<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // QC - Inspection Types
            [
                'group' => 'qc',
                'key' => 'inspection_types',
                'value' => [
                    'visual' => 'Visual Inspection',
                    'dimensional' => 'Dimensional Inspection',
                    'functional' => 'Functional Test',
                    'documentation' => 'Documentation Check',
                    'sampling' => 'Sample Testing',
                ],
                'description' => 'Available inspection types for acceptance rules',
                'is_system' => true,
            ],
            // QC - Sampling Methods
            [
                'group' => 'qc',
                'key' => 'sampling_methods',
                'value' => [
                    '100_percent' => '100% Inspection',
                    'aql' => 'AQL Sampling',
                    'random' => 'Random Sampling',
                    'skip_lot' => 'Skip Lot',
                ],
                'description' => 'Available sampling methods for inspections',
                'is_system' => true,
            ],
            // QC - Inspection Results
            [
                'group' => 'qc',
                'key' => 'inspection_results',
                'value' => [
                    'pending' => 'Pending',
                    'pass' => 'Pass',
                    'fail' => 'Fail',
                    'conditional_pass' => 'Conditional Pass',
                ],
                'description' => 'Possible inspection result statuses',
                'is_system' => true,
            ],
            // QC - Dispositions
            [
                'group' => 'qc',
                'key' => 'dispositions',
                'value' => [
                    'accept' => 'Accept',
                    'reject' => 'Reject',
                    'hold' => 'Hold for Review',
                    'rework' => 'Rework Required',
                    'return_to_supplier' => 'Return to Supplier',
                    'use_as_is' => 'Use As-Is',
                ],
                'description' => 'Disposition options for inspected items',
                'is_system' => true,
            ],
            // QC - Defect Types
            [
                'group' => 'qc',
                'key' => 'defect_types',
                'value' => [
                    'physical_damage' => 'Physical Damage',
                    'dimensional_error' => 'Dimensional Error',
                    'cosmetic_defect' => 'Cosmetic Defect',
                    'functional_failure' => 'Functional Failure',
                    'material_defect' => 'Material Defect',
                    'contamination' => 'Contamination',
                    'packaging_damage' => 'Packaging Damage',
                    'documentation_error' => 'Documentation Error',
                    'labeling_error' => 'Labeling Error',
                    'quantity_discrepancy' => 'Quantity Discrepancy',
                    'quality_deviation' => 'Quality Deviation',
                    'other' => 'Other',
                ],
                'description' => 'Types of defects for NCR classification',
                'is_system' => true,
            ],
            // QC - NCR Severities
            [
                'group' => 'qc',
                'key' => 'ncr_severities',
                'value' => [
                    'critical' => 'Critical',
                    'major' => 'Major',
                    'minor' => 'Minor',
                ],
                'description' => 'Severity levels for non-conformance reports',
                'is_system' => true,
            ],
            // QC - NCR Statuses
            [
                'group' => 'qc',
                'key' => 'ncr_statuses',
                'value' => [
                    'draft' => 'Draft',
                    'open' => 'Open',
                    'under_review' => 'Under Review',
                    'pending_disposition' => 'Pending Disposition',
                    'in_progress' => 'In Progress',
                    'closed' => 'Closed',
                    'cancelled' => 'Cancelled',
                ],
                'description' => 'Status workflow for NCR',
                'is_system' => true,
            ],
            // QC - AQL Levels
            [
                'group' => 'qc',
                'key' => 'aql_levels',
                'value' => [
                    'S-1' => 'Special Level S-1',
                    'S-2' => 'Special Level S-2',
                    'S-3' => 'Special Level S-3',
                    'S-4' => 'Special Level S-4',
                    'I' => 'General Level I',
                    'II' => 'General Level II',
                    'III' => 'General Level III',
                ],
                'description' => 'AQL inspection levels (ANSI/ASQ Z1.4)',
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
