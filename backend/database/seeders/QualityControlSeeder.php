<?php

namespace Database\Seeders;

use App\Models\AcceptanceRule;
use App\Models\Category;
use App\Models\Company;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Enums\NcrDisposition;
use App\Enums\NcrSeverity;
use App\Enums\NcrStatus;
use App\Models\NonConformanceReport;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingInspection;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class QualityControlSeeder extends Seeder
{
    private int $grnCounter = 1;
    private int $poCounter = 1;

    /**
     * Run the database seeds.
     *
     * This seeder creates test data for Quality Control scenarios:
     * 1. Standard Receiving - Successful Pass
     * 2. Partial Failure - NCR Required
     * 3. Critical Quality Issue - Quarantine
     * 4. Dimensional Error - Rework
     * 5. Use-As-Is Decision - Conditional Acceptance
     * 6. Supplier Return - RMA Process
     * 7. Skip-Lot Inspection
     * 8. Multiple Defects - Complex NCR
     */
    public function run(): void
    {
        $company = Company::first();
        $user = User::where('company_id', $company->id)->first();
        $qcManager = User::where('company_id', $company->id)->skip(1)->first() ?? $user;

        $products = Product::where('company_id', $company->id)->take(8)->get();
        $suppliers = Supplier::where('company_id', $company->id)->get();
        $warehouse = Warehouse::where('company_id', $company->id)->first();
        $categories = Category::where('company_id', $company->id)->take(3)->get();
        $uom = UnitOfMeasure::where('company_id', $company->id)->where('code', 'pcs')->first();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please run ProductSeeder first.');
            return;
        }

        if (!$uom) {
            $this->command->warn('No unit of measure found. Please run UnitOfMeasureSeeder first.');
            return;
        }

        // Create Acceptance Rules
        $rules = $this->createAcceptanceRules($company, $user, $products, $suppliers, $categories);

        // Create test scenarios
        $this->createScenario1StandardPass($company, $user, $products[0] ?? null, $suppliers[0] ?? null, $warehouse, $rules['aql'], $uom);
        $this->createScenario2PartialFailure($company, $user, $qcManager, $products[1] ?? null, $suppliers[1] ?? null, $warehouse, $rules['visual'], $uom);
        $this->createScenario3CriticalQuarantine($company, $user, $qcManager, $products[2] ?? null, $suppliers[2] ?? null, $warehouse, $rules['visual'], $uom);
        $this->createScenario4DimensionalRework($company, $user, $qcManager, $products[3] ?? null, $suppliers[0] ?? null, $warehouse, $rules['dimensional'], $uom);
        $this->createScenario5UseAsIs($company, $user, $qcManager, $products[4] ?? null, $suppliers[1] ?? null, $warehouse, $rules['visual'], $uom);
        $this->createScenario6SupplierReturn($company, $user, $qcManager, $products[5] ?? null, $suppliers[2] ?? null, $warehouse, $rules['documentation'], $uom);
        $this->createScenario7SkipLot($company, $user, $products[6] ?? null, $suppliers[4] ?? null, $warehouse, $rules['skip_lot'], $uom);
        $this->createScenario8ComplexNcr($company, $user, $qcManager, $products[7] ?? null, $suppliers[3] ?? null, $warehouse, $rules['aql'], $uom);

        $this->command->info('Quality Control scenarios seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('  - ' . AcceptanceRule::count() . ' acceptance rules');
        $this->command->info('  - ' . GoodsReceivedNote::count() . ' goods received notes');
        $this->command->info('  - ' . ReceivingInspection::count() . ' receiving inspections');
        $this->command->info('  - ' . NonConformanceReport::count() . ' non-conformance reports');
    }

    /**
     * Create a PO, GRN with items for test scenarios
     */
    private function createGrn(
        Company $company,
        User $user,
        Product $product,
        ?Supplier $supplier,
        Warehouse $warehouse,
        UnitOfMeasure $uom,
        float $quantity,
        string $status,
        string $lotNumber,
        ?Carbon $receivedDate = null
    ): array {
        $receivedDate = $receivedDate ?? now();
        $unitPrice = 25.00;

        // Create Purchase Order first
        $poNumber = 'PO-QC-' . str_pad($this->poCounter++, 4, '0', STR_PAD_LEFT);

        $po = PurchaseOrder::create([
            'company_id' => $company->id,
            'order_number' => $poNumber,
            'supplier_id' => $supplier?->id ?? Supplier::where('company_id', $company->id)->first()->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => $receivedDate->copy()->subDays(7),
            'expected_delivery_date' => $receivedDate,
            'actual_delivery_date' => $receivedDate,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'currency' => 'USD',
            'exchange_rate' => 1.0,
            'subtotal' => $quantity * $unitPrice,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'other_charges' => 0,
            'total_amount' => $quantity * $unitPrice,
            'payment_terms' => 'Net 30',
            'payment_due_days' => 30,
            'notes' => 'QC Test Scenario PO',
            'created_by' => $user->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'line_number' => 1,
            'description' => $product->name,
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'quantity_cancelled' => 0,
            'uom_id' => $uom->id,
            'unit_price' => $unitPrice,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'line_total' => $quantity * $unitPrice,
            'expected_delivery_date' => $receivedDate,
            'lot_number' => $lotNumber,
        ]);

        // Create GRN
        $grnNumber = 'GRN-QC-' . str_pad($this->grnCounter++, 4, '0', STR_PAD_LEFT);

        $grn = GoodsReceivedNote::create([
            'company_id' => $company->id,
            'grn_number' => $grnNumber,
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier?->id ?? $po->supplier_id,
            'warehouse_id' => $warehouse->id,
            'received_date' => $receivedDate,
            'status' => $status,
            'requires_inspection' => true,
            'notes' => 'QC Test Scenario GRN',
            'received_by' => $user->id,
            'created_by' => $user->id,
        ]);

        $grnItem = GoodsReceivedNoteItem::create([
            'goods_received_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'line_number' => 1,
            'quantity_received' => $quantity,
            'quantity_accepted' => 0,
            'quantity_rejected' => 0,
            'uom_id' => $uom->id,
            'unit_cost' => $unitPrice,
            'total_cost' => $quantity * $unitPrice,
            'lot_number' => $lotNumber,
            'inspection_status' => 'pending',
        ]);

        return [$grn, $grnItem];
    }

    /**
     * Create acceptance rules for different scenarios
     */
    private function createAcceptanceRules(Company $company, User $user, $products, $suppliers, $categories): array
    {
        $rules = [];

        // Default AQL rule
        $rules['aql'] = AcceptanceRule::firstOrCreate(
            ['company_id' => $company->id, 'rule_code' => 'AR-0001'],
            [
                'name' => 'Standard AQL Inspection',
                'description' => 'Default AQL Level II inspection for general merchandise',
                'inspection_type' => 'sampling',
                'sampling_method' => 'aql',
                'aql_level' => 'II',
                'aql_value' => 2.5,
                'criteria' => [
                    'check_quantity' => true,
                    'check_packaging' => true,
                    'check_labeling' => true,
                    'check_documentation' => true,
                ],
                'is_default' => true,
                'is_active' => true,
                'priority' => 0,
                'created_by' => $user->id,
            ]
        );

        // Visual inspection rule
        $rules['visual'] = AcceptanceRule::firstOrCreate(
            ['company_id' => $company->id, 'rule_code' => 'AR-0002'],
            [
                'name' => '100% Visual Inspection',
                'description' => 'Complete visual inspection for high-value items',
                'inspection_type' => 'visual',
                'sampling_method' => '100_percent',
                'criteria' => [
                    'check_surface' => true,
                    'check_color' => true,
                    'check_finish' => true,
                    'check_assembly' => true,
                    'defect_tolerance' => 0,
                ],
                'is_default' => false,
                'is_active' => true,
                'priority' => 10,
                'created_by' => $user->id,
            ]
        );

        // Dimensional inspection rule
        $rules['dimensional'] = AcceptanceRule::firstOrCreate(
            ['company_id' => $company->id, 'rule_code' => 'AR-0003'],
            [
                'name' => 'Dimensional Check',
                'description' => 'Dimensional verification for precision components',
                'inspection_type' => 'dimensional',
                'sampling_method' => 'aql',
                'aql_level' => 'II',
                'aql_value' => 1.0,
                'criteria' => [
                    'tolerance_mm' => 0.5,
                    'check_dimensions' => ['length', 'width', 'height', 'diameter'],
                    'measurement_tools' => ['caliper', 'micrometer'],
                ],
                'is_default' => false,
                'is_active' => true,
                'priority' => 20,
                'created_by' => $user->id,
            ]
        );

        // Documentation check rule
        $rules['documentation'] = AcceptanceRule::firstOrCreate(
            ['company_id' => $company->id, 'rule_code' => 'AR-0004'],
            [
                'name' => 'Documentation Verification',
                'description' => 'Full documentation and certification check',
                'inspection_type' => 'documentation',
                'sampling_method' => '100_percent',
                'criteria' => [
                    'required_documents' => [
                        'packing_list',
                        'certificate_of_conformance',
                        'test_report',
                        'material_certificate',
                    ],
                    'verify_po_match' => true,
                    'verify_quantities' => true,
                ],
                'is_default' => false,
                'is_active' => true,
                'priority' => 5,
                'created_by' => $user->id,
            ]
        );

        // Skip-lot for trusted supplier
        if ($suppliers->count() >= 5) {
            $rules['skip_lot'] = AcceptanceRule::firstOrCreate(
                ['company_id' => $company->id, 'rule_code' => 'AR-0005'],
                [
                    'supplier_id' => $suppliers[4]->id,
                    'name' => 'Skip-Lot for Trusted Supplier',
                    'description' => 'Reduced inspection for Local Supplies Turkey - 5-star rating',
                    'inspection_type' => 'documentation',
                    'sampling_method' => 'skip_lot',
                    'criteria' => [
                        'verify_documentation_only' => true,
                        'random_audit_frequency' => 10, // 1 in 10 lots
                    ],
                    'is_default' => false,
                    'is_active' => true,
                    'priority' => 100,
                    'created_by' => $user->id,
                ]
            );
        } else {
            $rules['skip_lot'] = $rules['aql'];
        }

        // Category-specific rule (if categories exist)
        if ($categories->isNotEmpty()) {
            $rules['category'] = AcceptanceRule::firstOrCreate(
                ['company_id' => $company->id, 'rule_code' => 'AR-0006'],
                [
                    'category_id' => $categories[0]->id,
                    'name' => 'Electronics Category Rule',
                    'description' => 'Functional testing for electronics category',
                    'inspection_type' => 'functional',
                    'sampling_method' => 'random',
                    'sample_size_percentage' => 10,
                    'criteria' => [
                        'power_on_test' => true,
                        'functionality_check' => true,
                        'safety_test' => true,
                    ],
                    'is_default' => false,
                    'is_active' => true,
                    'priority' => 50,
                    'created_by' => $user->id,
                ]
            );
        }

        return $rules;
    }

    /**
     * Scenario 1: Standard Receiving - Successful Pass
     * AQL sampling inspection, all samples pass, stock released as available
     */
    private function createScenario1StandardPass($company, $user, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            500, GoodsReceivedNote::STATUS_COMPLETED, 'LOT-2024-001', now()->subDays(5)
        );

        // Update GRN item as accepted
        $grnItem->update([
            'quantity_accepted' => 500,
            'inspection_status' => 'passed',
        ]);

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0001',
            'lot_number' => 'LOT-2024-001',
            'batch_number' => 'BATCH-001',
            'quantity_received' => 500,
            'quantity_inspected' => 50,  // AQL sample size
            'quantity_passed' => 50,
            'quantity_failed' => 0,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_PASSED,
            'disposition' => ReceivingInspection::DISPOSITION_ACCEPT,
            'inspection_data' => [
                'sampling_method' => 'aql',
                'aql_level' => 'II',
                'sample_size' => 50,
                'accept_number' => 3,
                'reject_number' => 4,
                'defects_found' => 0,
                'measurements' => [
                    'visual_check' => 'pass',
                    'packaging_check' => 'pass',
                    'documentation_check' => 'pass',
                ],
            ],
            'notes' => 'All samples passed AQL inspection. Stock released for sale.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subDays(5),
            'approved_by' => $user->id,
            'approved_at' => now()->subDays(5),
        ]);

        // Create available stock
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-001',
            'quantity_on_hand' => 500,
            'quantity_reserved' => 0,
            'unit_cost' => 25.00,
            'received_date' => now()->subDays(5),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_AVAILABLE,
        ]);
    }

    /**
     * Scenario 2: Partial Failure - NCR Required
     * Packaging damage found, NCR created, return to supplier disposition
     */
    private function createScenario2PartialFailure($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            200, GoodsReceivedNote::STATUS_COMPLETED, 'LOT-2024-002', now()->subDays(3)
        );

        // Update GRN item
        $grnItem->update([
            'quantity_accepted' => 170,
            'quantity_rejected' => 30,
            'inspection_status' => 'partial',
            'rejection_reason' => 'Packaging damage - 30 units',
        ]);

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0002',
            'lot_number' => 'LOT-2024-002',
            'batch_number' => 'BATCH-002',
            'quantity_received' => 200,
            'quantity_inspected' => 200,
            'quantity_passed' => 170,
            'quantity_failed' => 30,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_PARTIAL,
            'disposition' => ReceivingInspection::DISPOSITION_ACCEPT,  // Passed items accepted
            'inspection_data' => [
                'defects_found' => 30,
                'defect_details' => [
                    ['type' => 'packaging_damage', 'count' => 25],
                    ['type' => 'cosmetic_defect', 'count' => 5],
                ],
            ],
            'failure_reason' => 'Packaging damage on 30 units during shipping',
            'notes' => 'Passed items accepted. NCR created for failed items.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subDays(3),
            'approved_by' => $qcManager->id,
            'approved_at' => now()->subDays(3),
        ]);

        // Create NCR for failed items
        NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0001',
            'title' => 'Packaging Damage - Shipment LOT-2024-002',
            'description' => 'Multiple units received with damaged packaging. 25 units have crushed outer boxes, 5 units have cosmetic damage to product.',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-002',
            'batch_number' => 'BATCH-002',
            'quantity_affected' => 30,
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::MAJOR->value,
            'priority' => 'high',
            'defect_type' => 'packaging',
            'root_cause' => 'Improper handling during transit. Evidence of forklift damage on pallets.',
            'disposition' => NcrDisposition::RETURN_TO_SUPPLIER->value,
            'disposition_reason' => 'Units not suitable for sale. Return to supplier for credit.',
            'cost_impact' => 750.00,
            'cost_currency' => 'USD',
            'status' => NcrStatus::CLOSED->value,
            'reported_by' => $user->id,
            'reported_at' => now()->subDays(3),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subDays(2),
            'disposition_by' => $qcManager->id,
            'disposition_at' => now()->subDays(2),
            'closed_by' => $qcManager->id,
            'closed_at' => now()->subDays(1),
            'closure_notes' => 'RMA processed. Credit memo #CM-2024-0015 received from supplier.',
        ]);

        // Create stock for passed items only
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-002',
            'quantity_on_hand' => 170,
            'quantity_reserved' => 0,
            'unit_cost' => 25.00,
            'received_date' => now()->subDays(3),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_AVAILABLE,
        ]);
    }

    /**
     * Scenario 3: Critical Quality Issue - Quarantine
     * Contamination found, stock quarantined, held for lab testing
     */
    private function createScenario3CriticalQuarantine($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            1000, GoodsReceivedNote::STATUS_PENDING_INSPECTION, 'LOT-2024-003', now()->subHours(12)
        );

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0003',
            'lot_number' => 'LOT-2024-003',
            'batch_number' => 'BATCH-003',
            'quantity_received' => 1000,
            'quantity_inspected' => 100,
            'quantity_passed' => 0,
            'quantity_failed' => 100,
            'quantity_on_hold' => 900,
            'result' => ReceivingInspection::RESULT_ON_HOLD,
            'disposition' => ReceivingInspection::DISPOSITION_PENDING,
            'inspection_data' => [
                'contamination_type' => 'foreign_material',
                'contamination_source' => 'unknown',
                'lab_test_required' => true,
                'lab_test_reference' => 'LAB-2024-0042',
            ],
            'failure_reason' => 'Foreign material contamination detected in sampled units',
            'notes' => 'CRITICAL: All stock quarantined pending lab analysis. Do not release.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subHours(12),
        ]);

        // Create NCR for contamination
        $ncr = NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0002',
            'title' => 'CRITICAL: Contamination - LOT-2024-003',
            'description' => 'Foreign material detected during visual inspection. Unknown dark particles found inside sealed product packaging. Entire lot quarantined pending laboratory analysis.',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-003',
            'batch_number' => 'BATCH-003',
            'quantity_affected' => 1000,
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::CRITICAL->value,
            'priority' => 'urgent',
            'defect_type' => 'contamination',
            'disposition' => NcrDisposition::PENDING->value,
            'status' => NcrStatus::UNDER_REVIEW->value,
            'attachments' => [
                ['name' => 'contamination_photo_1.jpg', 'type' => 'image'],
                ['name' => 'contamination_photo_2.jpg', 'type' => 'image'],
                ['name' => 'lab_request_form.pdf', 'type' => 'document'],
            ],
            'reported_by' => $user->id,
            'reported_at' => now()->subHours(12),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subHours(6),
        ]);

        // Create quarantined stock
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-003',
            'quantity_on_hand' => 1000,
            'quantity_reserved' => 0,
            'unit_cost' => 15.00,
            'received_date' => now()->subHours(12),
            'status' => Stock::STATUS_QUARANTINE,
            'quality_status' => Stock::QUALITY_QUARANTINE,
            'hold_reason' => 'Critical contamination - pending lab analysis',
            'hold_until' => now()->addDays(7),
            'quality_hold_by' => $qcManager->id,
            'quality_hold_at' => now()->subHours(6),
            'quality_reference_type' => NonConformanceReport::class,
            'quality_reference_id' => $ncr->id,
        ]);
    }

    /**
     * Scenario 4: Dimensional Error - Rework
     * Out-of-tolerance components, sent for rework then reinspected
     */
    private function createScenario4DimensionalRework($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            100, GoodsReceivedNote::STATUS_PENDING_INSPECTION, 'LOT-2024-004', now()->subDays(2)
        );

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0004',
            'lot_number' => 'LOT-2024-004',
            'batch_number' => 'BATCH-004',
            'quantity_received' => 100,
            'quantity_inspected' => 20,
            'quantity_passed' => 12,
            'quantity_failed' => 8,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_PARTIAL,
            'disposition' => ReceivingInspection::DISPOSITION_REWORK,
            'inspection_data' => [
                'measurement_type' => 'dimensional',
                'specification' => ['diameter' => '25.0mm', 'tolerance' => '±0.5mm'],
                'measurements' => [
                    ['sample' => 1, 'value' => '25.1mm', 'result' => 'pass'],
                    ['sample' => 2, 'value' => '24.8mm', 'result' => 'pass'],
                    ['sample' => 3, 'value' => '25.9mm', 'result' => 'fail'],
                    ['sample' => 4, 'value' => '26.2mm', 'result' => 'fail'],
                ],
                'failure_rate' => '40%',
                'tools_used' => ['digital_caliper', 'go_no_go_gauge'],
            ],
            'failure_reason' => 'Diameter out of tolerance - exceeds +0.5mm limit',
            'notes' => 'Failed units sent to rework station for grinding to specification.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subDays(2),
            'approved_by' => $qcManager->id,
            'approved_at' => now()->subDays(2),
        ]);

        // NCR for dimensional issue
        NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0003',
            'title' => 'Dimensional Deviation - Oversized Diameter',
            'description' => 'Sample inspection revealed 40% failure rate for diameter tolerance. Measured values exceed upper specification limit of 25.5mm.',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-004',
            'batch_number' => 'BATCH-004',
            'quantity_affected' => 40,  // Estimated 40% of total
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::MAJOR->value,
            'priority' => 'high',
            'defect_type' => 'dimensional',
            'root_cause' => 'Supplier tool wear - diameter grinding wheel needs replacement',
            'disposition' => NcrDisposition::REWORK->value,
            'disposition_reason' => 'Rework feasible - grind to specification and reinspect',
            'cost_impact' => 200.00,
            'cost_currency' => 'USD',
            'status' => NcrStatus::IN_PROGRESS->value,
            'reported_by' => $user->id,
            'reported_at' => now()->subDays(2),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subDays(2),
            'disposition_by' => $qcManager->id,
            'disposition_at' => now()->subDays(1),
        ]);

        // Stock on hold for rework
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-004',
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'unit_cost' => 50.00,
            'received_date' => now()->subDays(2),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_ON_HOLD,
            'hold_reason' => 'Pending rework - dimensional deviation',
            'quality_hold_by' => $qcManager->id,
            'quality_hold_at' => now()->subDays(2),
        ]);
    }

    /**
     * Scenario 5: Use-As-Is Decision - Conditional Acceptance
     * Minor cosmetic defect, accepted with restrictions for production only
     */
    private function createScenario5UseAsIs($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            300, GoodsReceivedNote::STATUS_COMPLETED, 'LOT-2024-005', now()->subDays(4)
        );

        // Update GRN item as conditionally accepted
        $grnItem->update([
            'quantity_accepted' => 300,
            'inspection_status' => 'passed',
            'inspection_notes' => 'Conditional acceptance - production use only',
        ]);

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0005',
            'lot_number' => 'LOT-2024-005',
            'batch_number' => 'BATCH-005',
            'quantity_received' => 300,
            'quantity_inspected' => 300,
            'quantity_passed' => 250,
            'quantity_failed' => 50,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_PARTIAL,
            'disposition' => ReceivingInspection::DISPOSITION_USE_AS_IS,
            'inspection_data' => [
                'defect_type' => 'cosmetic',
                'defect_description' => 'Minor surface scratches on non-visible area',
                'functional_impact' => 'none',
                'engineering_review' => true,
                'engineering_approval' => 'ENG-2024-0123',
            ],
            'failure_reason' => 'Minor scratches on internal component surface',
            'notes' => 'Engineering approved use-as-is. Scratches on internal surface, not visible in final assembly.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subDays(4),
            'approved_by' => $qcManager->id,
            'approved_at' => now()->subDays(4),
        ]);

        // NCR with use-as-is disposition
        NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0004',
            'title' => 'Cosmetic Defect - Surface Scratches',
            'description' => 'Minor surface scratches detected on internal component surface. Scratches are on the side that faces inward during assembly and will not be visible in final product.',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-005',
            'batch_number' => 'BATCH-005',
            'quantity_affected' => 50,
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::MINOR->value,
            'priority' => 'low',
            'defect_type' => 'visual',
            'root_cause' => 'Handling marks during supplier packaging process',
            'disposition' => NcrDisposition::USE_AS_IS->value,
            'disposition_reason' => 'Engineering approval granted. Defect does not affect form, fit, or function. Restricted to production use only - not for direct sale.',
            'cost_impact' => 0.00,
            'cost_currency' => 'USD',
            'status' => NcrStatus::CLOSED->value,
            'reported_by' => $user->id,
            'reported_at' => now()->subDays(4),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subDays(4),
            'disposition_by' => $qcManager->id,
            'disposition_at' => now()->subDays(3),
            'closed_by' => $qcManager->id,
            'closed_at' => now()->subDays(3),
            'closure_notes' => 'Accepted per engineering deviation #ENG-2024-0123',
        ]);

        // Create conditional stock with restrictions
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-005',
            'quantity_on_hand' => 300,
            'quantity_reserved' => 0,
            'unit_cost' => 35.00,
            'received_date' => now()->subDays(4),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_CONDITIONAL,
            'hold_reason' => 'Use-as-is: Production use only, not for direct sale',
            'quality_restrictions' => [
                'allowed_operations' => ['production', 'bundle'],
                'blocked_operations' => ['sale'],
                'notes' => 'Engineering approved for internal assembly only',
                'deviation_reference' => 'ENG-2024-0123',
            ],
            'quality_hold_by' => $qcManager->id,
            'quality_hold_at' => now()->subDays(3),
        ]);
    }

    /**
     * Scenario 6: Supplier Return - RMA Process
     * Wrong product delivered, full rejection and RMA
     */
    private function createScenario6SupplierReturn($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            150, GoodsReceivedNote::STATUS_COMPLETED, 'LOT-2024-006', now()->subDays(1)
        );

        // Update GRN item as rejected
        $grnItem->update([
            'quantity_rejected' => 150,
            'inspection_status' => 'failed',
            'rejection_reason' => 'Wrong product delivered',
        ]);

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0006',
            'lot_number' => 'LOT-2024-006',
            'batch_number' => 'BATCH-006',
            'quantity_received' => 150,
            'quantity_inspected' => 150,
            'quantity_passed' => 0,
            'quantity_failed' => 150,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_FAILED,
            'disposition' => ReceivingInspection::DISPOSITION_RETURN,
            'inspection_data' => [
                'documentation_match' => false,
                'product_match' => false,
                'po_reference' => 'PO-2024-0789',
                'shipped_product' => 'MODEL-ABC',
                'ordered_product' => 'MODEL-XYZ',
            ],
            'failure_reason' => 'Wrong product delivered - MODEL-ABC shipped instead of MODEL-XYZ',
            'notes' => 'Complete shipment rejected. RMA initiated for supplier pickup.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subDays(1),
            'approved_by' => $qcManager->id,
            'approved_at' => now()->subDays(1),
        ]);

        // NCR for wrong item
        NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0005',
            'title' => 'Wrong Product Shipped - Complete Rejection',
            'description' => 'Supplier shipped incorrect product. PO-2024-0789 specified MODEL-XYZ but MODEL-ABC was received. Products are not interchangeable.',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-006',
            'batch_number' => 'BATCH-006',
            'quantity_affected' => 150,
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::MAJOR->value,
            'priority' => 'urgent',
            'defect_type' => 'wrong_item',
            'root_cause' => 'Supplier shipping error - wrong SKU picked from warehouse',
            'disposition' => NcrDisposition::RETURN_TO_SUPPLIER->value,
            'disposition_reason' => 'Full rejection. RMA #RMA-2024-0033 issued. Supplier to arrange pickup.',
            'cost_impact' => 0.00,  // No cost impact - returned
            'cost_currency' => 'USD',
            'status' => NcrStatus::IN_PROGRESS->value,
            'attachments' => [
                ['name' => 'packing_slip.pdf', 'type' => 'document'],
                ['name' => 'po_comparison.pdf', 'type' => 'document'],
            ],
            'reported_by' => $user->id,
            'reported_at' => now()->subDays(1),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subDays(1),
            'disposition_by' => $qcManager->id,
            'disposition_at' => now()->subHours(12),
        ]);

        // Rejected stock pending return
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-006',
            'quantity_on_hand' => 150,
            'quantity_reserved' => 0,
            'unit_cost' => 45.00,
            'received_date' => now()->subDays(1),
            'status' => Stock::STATUS_QUARANTINE,
            'quality_status' => Stock::QUALITY_REJECTED,
            'hold_reason' => 'Wrong product - pending RMA return to supplier',
            'quality_hold_by' => $qcManager->id,
            'quality_hold_at' => now()->subDays(1),
        ]);
    }

    /**
     * Scenario 7: Skip-Lot Inspection
     * Trusted supplier (5-star rating), documentation check only
     */
    private function createScenario7SkipLot($company, $user, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            1000, GoodsReceivedNote::STATUS_COMPLETED, 'LOT-2024-007', now()->subHours(4)
        );

        // Update GRN item as accepted
        $grnItem->update([
            'quantity_accepted' => 1000,
            'inspection_status' => 'passed',
            'inspection_notes' => 'Skip-lot inspection - documentation verified',
        ]);

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0007',
            'lot_number' => 'LOT-2024-007',
            'batch_number' => 'BATCH-007',
            'quantity_received' => 1000,
            'quantity_inspected' => 0,  // Skip lot - no physical inspection
            'quantity_passed' => 1000,
            'quantity_failed' => 0,
            'quantity_on_hold' => 0,
            'result' => ReceivingInspection::RESULT_PASSED,
            'disposition' => ReceivingInspection::DISPOSITION_ACCEPT,
            'inspection_data' => [
                'skip_lot' => true,
                'skip_reason' => 'Trusted supplier with 5-star rating',
                'documentation_verified' => true,
                'documents_checked' => [
                    'packing_list' => 'verified',
                    'invoice' => 'verified',
                    'certificate_of_conformance' => 'verified',
                ],
                'supplier_rating' => 5,
                'consecutive_pass_lots' => 24,
            ],
            'notes' => 'Skip-lot applied per AR-0005. Documentation verified, no physical inspection required.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subHours(4),
            'approved_by' => $user->id,
            'approved_at' => now()->subHours(4),
        ]);

        // Available stock - direct release
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-007',
            'quantity_on_hand' => 1000,
            'quantity_reserved' => 0,
            'unit_cost' => 12.00,
            'received_date' => now()->subHours(4),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_AVAILABLE,
            'notes' => 'Skip-lot inspection - released immediately',
        ]);
    }

    /**
     * Scenario 8: Multiple Defects - Complex NCR
     * Multiple defect types found, comprehensive NCR with mixed dispositions
     */
    private function createScenario8ComplexNcr($company, $user, $qcManager, $product, $supplier, $warehouse, $rule, $uom): void
    {
        if (!$product) return;

        [$grn, $grnItem] = $this->createGrn(
            $company, $user, $product, $supplier, $warehouse, $uom,
            500, GoodsReceivedNote::STATUS_PENDING_INSPECTION, 'LOT-2024-008', now()->subHours(8)
        );

        $inspection = ReceivingInspection::create([
            'company_id' => $company->id,
            'goods_received_note_id' => $grn->id,
            'grn_item_id' => $grnItem->id,
            'product_id' => $product->id,
            'acceptance_rule_id' => $rule->id,
            'inspection_number' => 'INS-2024-0008',
            'lot_number' => 'LOT-2024-008',
            'batch_number' => 'BATCH-008',
            'quantity_received' => 500,
            'quantity_inspected' => 80,
            'quantity_passed' => 60,
            'quantity_failed' => 15,
            'quantity_on_hold' => 5,
            'result' => ReceivingInspection::RESULT_PARTIAL,
            'disposition' => ReceivingInspection::DISPOSITION_PENDING,
            'inspection_data' => [
                'defect_summary' => [
                    ['type' => 'dimensional', 'count' => 8, 'severity' => 'major'],
                    ['type' => 'cosmetic', 'count' => 5, 'severity' => 'minor'],
                    ['type' => 'documentation', 'count' => 2, 'severity' => 'minor'],
                    ['type' => 'contamination_suspect', 'count' => 5, 'severity' => 'unknown'],
                ],
                'aql_result' => 'reject',
                'recommended_action' => 'mixed_disposition',
            ],
            'failure_reason' => 'Multiple defect types detected - dimensional, cosmetic, documentation errors, and suspected contamination',
            'notes' => 'Complex NCR required. Material Review Board to evaluate mixed disposition options.',
            'inspected_by' => $user->id,
            'inspected_at' => now()->subHours(8),
        ]);

        // Primary NCR - Multiple defects
        $ncr = NonConformanceReport::create([
            'company_id' => $company->id,
            'source_type' => NonConformanceReport::SOURCE_RECEIVING,
            'receiving_inspection_id' => $inspection->id,
            'ncr_number' => 'NCR-2024-0006',
            'title' => 'Multiple Quality Issues - LOT-2024-008',
            'description' => 'Complex quality issues identified across multiple defect categories:
- 8 units with dimensional errors (major)
- 5 units with cosmetic defects (minor)
- 2 units with documentation discrepancies
- 5 units with suspected contamination requiring lab analysis',
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'lot_number' => 'LOT-2024-008',
            'batch_number' => 'BATCH-008',
            'quantity_affected' => 100,  // Estimated based on sample
            'unit_of_measure' => 'pcs',
            'severity' => NcrSeverity::MAJOR->value,
            'priority' => 'urgent',
            'defect_type' => 'other',
            'root_cause' => 'Under investigation - appears to be systemic quality control issue at supplier',
            'disposition' => NcrDisposition::PENDING->value,
            'status' => NcrStatus::PENDING_DISPOSITION->value,
            'attachments' => [
                ['name' => 'dimensional_report.pdf', 'type' => 'document'],
                ['name' => 'defect_photos.zip', 'type' => 'archive'],
                ['name' => 'supplier_coc.pdf', 'type' => 'document'],
                ['name' => 'lab_request.pdf', 'type' => 'document'],
            ],
            'reported_by' => $user->id,
            'reported_at' => now()->subHours(8),
            'reviewed_by' => $qcManager->id,
            'reviewed_at' => now()->subHours(4),
        ]);

        // Stock pending MRB decision - split into two lots
        // Good stock from initial samples
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-008-A',
            'quantity_on_hand' => 300,  // Estimated good stock
            'quantity_reserved' => 0,
            'unit_cost' => 28.00,
            'received_date' => now()->subHours(8),
            'status' => Stock::STATUS_AVAILABLE,
            'quality_status' => Stock::QUALITY_PENDING_INSPECTION,
            'hold_reason' => 'Pending 100% inspection to segregate good units',
            'quality_hold_by' => $user->id,
            'quality_hold_at' => now()->subHours(8),
        ]);

        // Suspect stock requiring further evaluation
        Stock::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'LOT-2024-008-B',
            'quantity_on_hand' => 200,  // Estimated suspect stock
            'quantity_reserved' => 0,
            'unit_cost' => 28.00,
            'received_date' => now()->subHours(8),
            'status' => Stock::STATUS_QUARANTINE,
            'quality_status' => Stock::QUALITY_ON_HOLD,
            'hold_reason' => 'MRB hold - pending disposition decision for mixed defects',
            'quality_hold_by' => $qcManager->id,
            'quality_hold_at' => now()->subHours(4),
            'quality_reference_type' => NonConformanceReport::class,
            'quality_reference_id' => $ncr->id,
        ]);
    }
}
