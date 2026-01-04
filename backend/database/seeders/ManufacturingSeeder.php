<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Product;
use App\Models\Routing;
use App\Models\RoutingOperation;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use App\Models\WorkOrderOperation;
use App\Enums\BomStatus;
use App\Enums\BomType;
use App\Enums\OperationStatus;
use App\Enums\RoutingStatus;
use App\Enums\WorkCenterType;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use Illuminate\Database\Seeder;

class ManufacturingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Manufacturing Data
     */
    public function run(): void
    {
        $this->command->info('Seeding Agricultural Machinery Manufacturing data...');

        // Get company_id from first user
        $companyId = \App\Models\User::first()?->company_id ?? 1;
        $userId = \App\Models\User::first()?->id ?? 1;

        // Get default UOM (piece)
        $pieceUom = UnitOfMeasure::where('code', 'pcs')->first();
        $uomId = $pieceUom?->id ?? 1;

        // Get WIP warehouse (Production Plant)
        $wipWarehouse = Warehouse::where('code', 'WH-PROD')->first()
            ?? Warehouse::where('warehouse_type', 'wip')->first();
        $warehouseId = $wipWarehouse?->id ?? 1;

        // Create Agricultural Machinery Work Centers
        $this->command->info('Creating Agricultural Machinery Work Centers...');

        $workCenters = [
            // ========================================
            // FABRICATION CENTERS
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-LASER-01',
                'name' => 'Laser Cutting Center',
                'description' => 'High-precision laser cutting for steel plates and tubes',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 185.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 92,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-PLASMA-01',
                'name' => 'Plasma Cutting Station',
                'description' => 'Heavy-duty plasma cutting for thick steel',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 145.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 88,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-BEND-01',
                'name' => 'CNC Press Brake',
                'description' => 'Hydraulic press brake for bending steel plates',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 125.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 90,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-CNC-01',
                'name' => 'CNC Machining Center',
                'description' => '5-axis CNC machining for precision components',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 175.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 85,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-LATHE-01',
                'name' => 'CNC Lathe Station',
                'description' => 'CNC lathe for shafts and cylindrical parts',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 145.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 88,
                'is_active' => true,
                'created_by' => $userId,
            ],

            // ========================================
            // WELDING & JOINING
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-WELD-01',
                'name' => 'Robot Welding Cell 1',
                'description' => 'Automated MIG/MAG welding for frame assemblies',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 165.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 94,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-WELD-02',
                'name' => 'Robot Welding Cell 2',
                'description' => 'Automated welding for implement frames',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 165.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 92,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-WELD-MAN',
                'name' => 'Manual Welding Station',
                'description' => 'Manual welding for complex and custom assemblies',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 85.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 80,
                'is_active' => true,
                'created_by' => $userId,
            ],

            // ========================================
            // SURFACE TREATMENT
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-BLAST-01',
                'name' => 'Shot Blasting Chamber',
                'description' => 'Surface preparation and rust removal',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 95.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 90,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-PAINT-01',
                'name' => 'Powder Coating Line',
                'description' => 'Electrostatic powder coating for corrosion protection',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 125.00,
                'capacity_per_day' => 16,
                'efficiency_percentage' => 88,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-PAINT-02',
                'name' => 'Wet Paint Booth',
                'description' => 'Spray painting for special finishes',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 75.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 85,
                'is_active' => true,
                'created_by' => $userId,
            ],

            // ========================================
            // ASSEMBLY LINES
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-TRAC',
                'name' => 'Tractor Assembly Line',
                'description' => 'Main tractor assembly and integration',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 95.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 88,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-IMPL',
                'name' => 'Implement Assembly Line',
                'description' => 'Assembly of soil and harvesting implements',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 85.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 90,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-HYD',
                'name' => 'Hydraulics Assembly',
                'description' => 'Hydraulic system assembly and testing',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 95.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 92,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-ELEC',
                'name' => 'Electrical Assembly',
                'description' => 'Wiring harness and electronics installation',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 85.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 90,
                'is_active' => true,
                'created_by' => $userId,
            ],

            // ========================================
            // TESTING & QC
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-TEST-01',
                'name' => 'Functional Test Station',
                'description' => 'Machine function and safety testing',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 75.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 95,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-QC-FINAL',
                'name' => 'Final Quality Inspection',
                'description' => 'Final quality check and certification',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 65.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 100,
                'is_active' => true,
                'created_by' => $userId,
            ],

            // ========================================
            // PDI & SHIPPING
            // ========================================
            [
                'company_id' => $companyId,
                'code' => 'WC-PDI-01',
                'name' => 'Pre-Delivery Inspection',
                'description' => 'Final PDI before customer delivery',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 55.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 98,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-PACK-01',
                'name' => 'Packaging & Crating',
                'description' => 'Export packaging and crating for shipping',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 45.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 92,
                'is_active' => true,
                'created_by' => $userId,
            ],
        ];

        foreach ($workCenters as $wcData) {
            WorkCenter::updateOrCreate(
                ['code' => $wcData['code'], 'company_id' => $wcData['company_id']],
                $wcData
            );
        }

        // Get manufacturable products (tractors and implements)
        $manufacturableProducts = Product::whereHas('productType', function ($q) {
            $q->where('can_be_manufactured', true);
        })->whereHas('categories', function ($q) {
            $q->whereIn('slug', [
                'compact-tractors', 'utility-tractors', 'row-crop-tractors',
                'ploughs', 'disc-harrows', 'cultivators', 'seed-drills',
                'field-sprayers', 'fertilizer-spreaders'
            ]);
        })->take(6)->get();

        if ($manufacturableProducts->isEmpty()) {
            $this->command->warn('No manufacturable products found. Skipping BOM and Routing creation.');
            return;
        }

        // Get component products (spare parts, raw materials)
        $componentProducts = Product::whereHas('categories', function ($q) {
            $q->whereIn('slug', [
                'steel-metals', 'fasteners', 'bearings-seals', 'rubber-plastics',
                'hydraulic-components', 'electrical-components', 'engine-parts'
            ]);
        })->take(30)->get();

        if ($componentProducts->count() < 5) {
            $this->command->warn('Not enough component products found. Skipping BOM creation.');
            return;
        }

        $this->command->info('Creating BOMs and Routings for Agricultural Machinery...');

        // Get work centers
        $laserCutting = WorkCenter::where('code', 'WC-LASER-01')->first();
        $bending = WorkCenter::where('code', 'WC-BEND-01')->first();
        $robotWelding = WorkCenter::where('code', 'WC-WELD-01')->first();
        $shotBlasting = WorkCenter::where('code', 'WC-BLAST-01')->first();
        $powderCoating = WorkCenter::where('code', 'WC-PAINT-01')->first();
        $tractorAssembly = WorkCenter::where('code', 'WC-ASM-TRAC')->first();
        $implementAssembly = WorkCenter::where('code', 'WC-ASM-IMPL')->first();
        $hydraulicsAssembly = WorkCenter::where('code', 'WC-ASM-HYD')->first();
        $functionalTest = WorkCenter::where('code', 'WC-TEST-01')->first();
        $finalQC = WorkCenter::where('code', 'WC-QC-FINAL')->first();
        $pdi = WorkCenter::where('code', 'WC-PDI-01')->first();

        $bomCounter = 1;
        $routingCounter = 1;
        $createdBoms = [];
        $createdRoutings = [];

        foreach ($manufacturableProducts as $index => $product) {
            // Create BOM
            $bomNumber = 'BOM-' . str_pad($bomCounter++, 5, '0', STR_PAD_LEFT);

            $bom = Bom::updateOrCreate(
                ['bom_number' => $bomNumber, 'company_id' => $companyId],
                [
                    'product_id' => $product->id,
                    'name' => $product->name . ' - Production BOM',
                    'description' => 'Manufacturing bill of materials for ' . $product->name,
                    'bom_type' => BomType::MANUFACTURING,
                    'status' => BomStatus::ACTIVE,
                    'quantity' => 1,
                    'uom_id' => $uomId,
                    'is_default' => true,
                    'created_by' => $userId,
                ]
            );

            $createdBoms[$product->id] = $bom;

            // Add BOM Items (components)
            $componentOffset = $index * 5;
            $availableComponents = $componentProducts->skip($componentOffset)->take(5);

            $lineNumber = 1;
            foreach ($availableComponents as $component) {
                if ($component->id === $product->id) continue;

                BomItem::updateOrCreate(
                    ['bom_id' => $bom->id, 'component_id' => $component->id],
                    [
                        'line_number' => $lineNumber++,
                        'quantity' => rand(2, 8),
                        'uom_id' => $uomId,
                        'scrap_percentage' => rand(2, 8),
                        'is_optional' => false,
                        'is_phantom' => false,
                    ]
                );
            }

            // Determine if this is a tractor or implement
            $isTractor = str_contains(strtolower($product->name), 'tractor') ||
                         str_contains(strtolower($product->name), 'at-') ||
                         str_contains(strtolower($product->name), 'ha-');

            // Create Routing
            $routingNumber = 'RTG-' . str_pad($routingCounter++, 5, '0', STR_PAD_LEFT);

            $routing = Routing::updateOrCreate(
                ['routing_number' => $routingNumber, 'company_id' => $companyId],
                [
                    'product_id' => $product->id,
                    'name' => $product->name . ' - Production Routing',
                    'description' => 'Manufacturing routing for ' . $product->name,
                    'status' => RoutingStatus::ACTIVE,
                    'is_default' => true,
                    'created_by' => $userId,
                ]
            );

            $createdRoutings[$product->id] = $routing;

            // Add Routing Operations based on product type
            if ($isTractor) {
                $operations = [
                    ['work_center_id' => $laserCutting?->id, 'operation_number' => 10, 'name' => 'Laser Cutting Frame Parts', 'description' => 'Cut chassis and frame components', 'setup_time' => 45, 'run_time_per_unit' => 120],
                    ['work_center_id' => $bending?->id, 'operation_number' => 20, 'name' => 'Bending & Forming', 'description' => 'Form structural components', 'setup_time' => 30, 'run_time_per_unit' => 60],
                    ['work_center_id' => $robotWelding?->id, 'operation_number' => 30, 'name' => 'Frame Welding', 'description' => 'Weld main chassis frame', 'setup_time' => 60, 'run_time_per_unit' => 180],
                    ['work_center_id' => $shotBlasting?->id, 'operation_number' => 40, 'name' => 'Shot Blasting', 'description' => 'Surface preparation', 'setup_time' => 15, 'run_time_per_unit' => 45],
                    ['work_center_id' => $powderCoating?->id, 'operation_number' => 50, 'name' => 'Powder Coating', 'description' => 'Apply protective coating', 'setup_time' => 20, 'run_time_per_unit' => 90],
                    ['work_center_id' => $tractorAssembly?->id, 'operation_number' => 60, 'name' => 'Tractor Assembly', 'description' => 'Main assembly - engine, transmission, cab', 'setup_time' => 30, 'run_time_per_unit' => 480],
                    ['work_center_id' => $hydraulicsAssembly?->id, 'operation_number' => 70, 'name' => 'Hydraulics Installation', 'description' => 'Install hydraulic system', 'setup_time' => 15, 'run_time_per_unit' => 120],
                    ['work_center_id' => $functionalTest?->id, 'operation_number' => 80, 'name' => 'Functional Testing', 'description' => 'Full function and safety test', 'setup_time' => 10, 'run_time_per_unit' => 180],
                    ['work_center_id' => $finalQC?->id, 'operation_number' => 90, 'name' => 'Final QC Inspection', 'description' => 'Quality certification', 'setup_time' => 5, 'run_time_per_unit' => 60],
                    ['work_center_id' => $pdi?->id, 'operation_number' => 100, 'name' => 'Pre-Delivery Inspection', 'description' => 'Final PDI before delivery', 'setup_time' => 5, 'run_time_per_unit' => 45],
                ];
            } else {
                $operations = [
                    ['work_center_id' => $laserCutting?->id, 'operation_number' => 10, 'name' => 'Laser Cutting', 'description' => 'Cut implement frame parts', 'setup_time' => 30, 'run_time_per_unit' => 60],
                    ['work_center_id' => $bending?->id, 'operation_number' => 20, 'name' => 'Bending & Forming', 'description' => 'Form structural parts', 'setup_time' => 20, 'run_time_per_unit' => 45],
                    ['work_center_id' => $robotWelding?->id, 'operation_number' => 30, 'name' => 'Welding Assembly', 'description' => 'Weld frame and components', 'setup_time' => 30, 'run_time_per_unit' => 120],
                    ['work_center_id' => $shotBlasting?->id, 'operation_number' => 40, 'name' => 'Shot Blasting', 'description' => 'Surface preparation', 'setup_time' => 10, 'run_time_per_unit' => 30],
                    ['work_center_id' => $powderCoating?->id, 'operation_number' => 50, 'name' => 'Powder Coating', 'description' => 'Apply protective finish', 'setup_time' => 15, 'run_time_per_unit' => 60],
                    ['work_center_id' => $implementAssembly?->id, 'operation_number' => 60, 'name' => 'Final Assembly', 'description' => 'Install working parts and hardware', 'setup_time' => 20, 'run_time_per_unit' => 180],
                    ['work_center_id' => $functionalTest?->id, 'operation_number' => 70, 'name' => 'Functional Testing', 'description' => 'Test all functions', 'setup_time' => 5, 'run_time_per_unit' => 45],
                    ['work_center_id' => $finalQC?->id, 'operation_number' => 80, 'name' => 'Final QC Inspection', 'description' => 'Quality certification', 'setup_time' => 5, 'run_time_per_unit' => 30],
                ];
            }

            foreach ($operations as $opData) {
                if ($opData['work_center_id']) {
                    RoutingOperation::updateOrCreate(
                        [
                            'routing_id' => $routing->id,
                            'operation_number' => $opData['operation_number'],
                        ],
                        array_merge($opData, [
                            'routing_id' => $routing->id,
                            'queue_time' => 15,
                            'move_time' => 10,
                        ])
                    );
                }
            }

            $this->command->info("Created BOM and Routing for: {$product->name}");
        }

        // Create Work Orders
        $this->command->info('Creating Agricultural Machinery Work Orders...');

        $workOrdersData = [
            [
                'product_index' => 0,
                'quantity' => 5,
                'priority' => WorkOrderPriority::NORMAL,
                'status' => WorkOrderStatus::COMPLETED,
                'notes' => 'Production batch completed - Q4 2024',
                'planned_start' => now()->subDays(14),
                'planned_end' => now()->subDays(7),
                'actual_start' => now()->subDays(14),
                'actual_end' => now()->subDays(8),
                'quantity_completed' => 5,
            ],
            [
                'product_index' => 1,
                'quantity' => 3,
                'priority' => WorkOrderPriority::HIGH,
                'status' => WorkOrderStatus::IN_PROGRESS,
                'notes' => 'Spring season production - Priority order for dealer',
                'planned_start' => now()->subDays(5),
                'planned_end' => now()->addDays(5),
                'actual_start' => now()->subDays(5),
                'actual_end' => null,
                'quantity_completed' => 1,
            ],
            [
                'product_index' => 2,
                'quantity' => 10,
                'priority' => WorkOrderPriority::NORMAL,
                'status' => WorkOrderStatus::RELEASED,
                'notes' => 'Stock replenishment for spring season',
                'planned_start' => now()->addDays(2),
                'planned_end' => now()->addDays(14),
                'actual_start' => null,
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
            [
                'product_index' => 3,
                'quantity' => 15,
                'priority' => WorkOrderPriority::LOW,
                'status' => WorkOrderStatus::DRAFT,
                'notes' => 'Planned production for Agritechnica exhibition',
                'planned_start' => now()->addDays(30),
                'planned_end' => now()->addDays(60),
                'actual_start' => null,
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
            [
                'product_index' => 4,
                'quantity' => 2,
                'priority' => WorkOrderPriority::URGENT,
                'status' => WorkOrderStatus::ON_HOLD,
                'notes' => 'On hold - Waiting for Grimme hydraulic components',
                'planned_start' => now()->subDays(7),
                'planned_end' => now()->addDays(3),
                'actual_start' => now()->subDays(7),
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
            [
                'product_index' => 5,
                'quantity' => 8,
                'priority' => WorkOrderPriority::NORMAL,
                'status' => WorkOrderStatus::RELEASED,
                'notes' => 'Export order - Belgium dealer',
                'planned_start' => now()->addDays(1),
                'planned_end' => now()->addDays(10),
                'actual_start' => null,
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
        ];

        $woCounter = 1;
        $productsArray = $manufacturableProducts->values();

        foreach ($workOrdersData as $woData) {
            if (!isset($productsArray[$woData['product_index']])) continue;

            $product = $productsArray[$woData['product_index']];
            $bom = $createdBoms[$product->id] ?? null;
            $routing = $createdRoutings[$product->id] ?? null;

            if (!$bom || !$routing) continue;

            $woNumber = 'WO-' . now()->format('Ym') . '-' . str_pad($woCounter++, 4, '0', STR_PAD_LEFT);

            $workOrder = WorkOrder::updateOrCreate(
                ['work_order_number' => $woNumber, 'company_id' => $companyId],
                [
                    'product_id' => $product->id,
                    'bom_id' => $bom->id,
                    'routing_id' => $routing->id,
                    'warehouse_id' => $warehouseId,
                    'quantity_ordered' => $woData['quantity'],
                    'quantity_completed' => $woData['quantity_completed'],
                    'quantity_scrapped' => 0,
                    'uom_id' => $uomId,
                    'status' => $woData['status'],
                    'priority' => $woData['priority'],
                    'planned_start_date' => $woData['planned_start'],
                    'planned_end_date' => $woData['planned_end'],
                    'actual_start_date' => $woData['actual_start'],
                    'actual_end_date' => $woData['actual_end'],
                    'notes' => $woData['notes'],
                    'estimated_cost' => $woData['quantity'] * ($product->cost_price ?? 25000),
                    'actual_cost' => $woData['quantity_completed'] * (($product->cost_price ?? 25000) * 0.95),
                    'created_by' => $userId,
                    'released_at' => in_array($woData['status'], [WorkOrderStatus::RELEASED, WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::COMPLETED, WorkOrderStatus::ON_HOLD]) ? now() : null,
                    'released_by' => in_array($woData['status'], [WorkOrderStatus::RELEASED, WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::COMPLETED, WorkOrderStatus::ON_HOLD]) ? $userId : null,
                    'completed_at' => $woData['status'] === WorkOrderStatus::COMPLETED ? $woData['actual_end'] : null,
                    'completed_by' => $woData['status'] === WorkOrderStatus::COMPLETED ? $userId : null,
                ]
            );

            // Create Work Order Operations
            $routingOps = RoutingOperation::where('routing_id', $routing->id)->orderBy('operation_number')->get();

            foreach ($routingOps as $routingOp) {
                $opStatus = OperationStatus::PENDING;
                $opQuantityCompleted = 0;
                $actualStart = null;
                $actualEnd = null;
                $actualSetup = 0;
                $actualRun = 0;

                if ($woData['status'] === WorkOrderStatus::COMPLETED) {
                    $opStatus = OperationStatus::COMPLETED;
                    $opQuantityCompleted = $woData['quantity'];
                    $actualStart = $woData['actual_start'];
                    $actualEnd = $woData['actual_end'];
                    $actualSetup = $routingOp->setup_time * 0.95;
                    $actualRun = $routingOp->run_time_per_unit * $woData['quantity'] * 0.98;
                } elseif ($woData['status'] === WorkOrderStatus::IN_PROGRESS) {
                    if ($routingOp->operation_number <= 30) {
                        $opStatus = OperationStatus::COMPLETED;
                        $opQuantityCompleted = $woData['quantity'];
                        $actualStart = $woData['actual_start'];
                        $actualEnd = now()->subDays(2);
                        $actualSetup = $routingOp->setup_time;
                        $actualRun = $routingOp->run_time_per_unit * $woData['quantity'];
                    } elseif ($routingOp->operation_number == 40) {
                        $opStatus = OperationStatus::IN_PROGRESS;
                        $opQuantityCompleted = $woData['quantity_completed'];
                        $actualStart = now()->subDays(1);
                    }
                }

                WorkOrderOperation::updateOrCreate(
                    [
                        'work_order_id' => $workOrder->id,
                        'operation_number' => $routingOp->operation_number,
                    ],
                    [
                        'routing_operation_id' => $routingOp->id,
                        'work_center_id' => $routingOp->work_center_id,
                        'name' => $routingOp->name,
                        'description' => $routingOp->description,
                        'status' => $opStatus,
                        'quantity_completed' => $opQuantityCompleted,
                        'quantity_scrapped' => 0,
                        'planned_start' => $woData['planned_start'],
                        'planned_end' => $woData['planned_end'],
                        'actual_start' => $actualStart,
                        'actual_end' => $actualEnd,
                        'actual_setup_time' => $actualSetup,
                        'actual_run_time' => $actualRun,
                        'actual_cost' => ($actualSetup + $actualRun) / 60 * ($routingOp->workCenter?->cost_per_hour ?? 100),
                    ]
                );
            }

            // Create Work Order Materials from BOM
            $bomItems = BomItem::where('bom_id', $bom->id)->get();

            foreach ($bomItems as $bomItem) {
                $requiredQty = $bomItem->quantity * $woData['quantity'] * (1 + $bomItem->scrap_percentage / 100);
                $issuedQty = 0;

                if ($woData['status'] === WorkOrderStatus::COMPLETED) {
                    $issuedQty = $requiredQty;
                } elseif (in_array($woData['status'], [WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::ON_HOLD])) {
                    $issuedQty = $requiredQty * ($woData['quantity_completed'] / max($woData['quantity'], 1));
                }

                $unitCost = $bomItem->component?->cost_price ?? rand(50, 500);

                WorkOrderMaterial::updateOrCreate(
                    [
                        'work_order_id' => $workOrder->id,
                        'product_id' => $bomItem->component_id,
                    ],
                    [
                        'bom_item_id' => $bomItem->id,
                        'quantity_required' => $requiredQty,
                        'quantity_issued' => $issuedQty,
                        'quantity_returned' => 0,
                        'uom_id' => $bomItem->uom_id,
                        'warehouse_id' => $warehouseId,
                        'unit_cost' => $unitCost,
                        'total_cost' => $issuedQty * $unitCost,
                    ]
                );
            }

            $this->command->info("Created Work Order: {$woNumber} ({$woData['status']->value})");
        }

        $this->command->info('Agricultural Machinery Manufacturing seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('  - Work Centers: ' . WorkCenter::where('company_id', $companyId)->count());
        $this->command->info('  - BOMs: ' . Bom::where('company_id', $companyId)->count());
        $this->command->info('  - Routings: ' . Routing::where('company_id', $companyId)->count());
        $this->command->info('  - Work Orders: ' . WorkOrder::where('company_id', $companyId)->count());
    }
}
