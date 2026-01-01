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
    public function run(): void
    {
        $this->command->info('Seeding Manufacturing data...');

        // Get company_id from first user
        $companyId = \App\Models\User::first()?->company_id ?? 1;
        $userId = \App\Models\User::first()?->id ?? 1;

        // Get default UOM (piece)
        $pieceUom = UnitOfMeasure::where('code', 'pcs')->first();
        $uomId = $pieceUom?->id ?? 1;

        // Get WIP warehouse
        $wipWarehouse = Warehouse::where('code', 'WH-WIP')->first();
        $warehouseId = $wipWarehouse?->id ?? 1;

        // Create Work Centers
        $this->command->info('Creating Work Centers...');

        $workCenters = [
            [
                'company_id' => $companyId,
                'code' => 'WC-CUT-01',
                'name' => 'Cutting Station 1',
                'description' => 'Primary cutting and sizing station',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 75.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 95,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-01',
                'name' => 'Assembly Station 1',
                'description' => 'Main assembly line station',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 45.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 90,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-ASM-02',
                'name' => 'Assembly Station 2',
                'description' => 'Secondary assembly line station',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 45.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 88,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-QC-01',
                'name' => 'Quality Control Station',
                'description' => 'Final quality inspection station',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 55.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 100,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-PKG-01',
                'name' => 'Packaging Station',
                'description' => 'Final packaging and labeling',
                'work_center_type' => WorkCenterType::LABOR,
                'cost_per_hour' => 35.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 92,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-CNC-01',
                'name' => 'CNC Machine 1',
                'description' => 'CNC machining center for precision parts',
                'work_center_type' => WorkCenterType::MACHINE,
                'cost_per_hour' => 120.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 85,
                'is_active' => true,
                'created_by' => $userId,
            ],
            [
                'company_id' => $companyId,
                'code' => 'WC-SUB-01',
                'name' => 'Subcontract - Painting',
                'description' => 'External painting and finishing services',
                'work_center_type' => WorkCenterType::SUBCONTRACT,
                'cost_per_hour' => 65.00,
                'capacity_per_day' => 8,
                'efficiency_percentage' => 100,
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

        // Get manufacturable products (product type with can_be_manufactured = true)
        $manufacturableProducts = Product::whereHas('productType', function ($q) {
            $q->where('can_be_manufactured', true);
        })->take(5)->get();

        if ($manufacturableProducts->isEmpty()) {
            $this->command->warn('No manufacturable products found. Skipping BOM and Routing creation.');
            return;
        }

        // Get component products
        $componentProducts = Product::whereHas('productType', function ($q) {
            $q->where('track_inventory', true);
        })->take(20)->get();

        if ($componentProducts->count() < 3) {
            $this->command->warn('Not enough component products found. Skipping BOM creation.');
            return;
        }

        $this->command->info('Creating BOMs and Routings...');

        // Get work centers
        $cuttingStation = WorkCenter::where('code', 'WC-CUT-01')->first();
        $assemblyStation1 = WorkCenter::where('code', 'WC-ASM-01')->first();
        $assemblyStation2 = WorkCenter::where('code', 'WC-ASM-02')->first();
        $qcStation = WorkCenter::where('code', 'WC-QC-01')->first();
        $packagingStation = WorkCenter::where('code', 'WC-PKG-01')->first();

        $bomCounter = 1;
        $routingCounter = 1;
        $createdBoms = [];
        $createdRoutings = [];

        foreach ($manufacturableProducts->take(3) as $index => $product) {
            // Create BOM
            $bomNumber = 'BOM-' . str_pad($bomCounter++, 5, '0', STR_PAD_LEFT);

            $bom = Bom::updateOrCreate(
                ['bom_number' => $bomNumber, 'company_id' => $companyId],
                [
                    'product_id' => $product->id,
                    'name' => $product->name . ' - Standard BOM',
                    'description' => 'Standard bill of materials for ' . $product->name,
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
            $componentOffset = $index * 3;
            $availableComponents = $componentProducts->skip($componentOffset)->take(3);

            $lineNumber = 1;
            foreach ($availableComponents as $component) {
                if ($component->id === $product->id) continue; // Skip if same as parent

                BomItem::updateOrCreate(
                    ['bom_id' => $bom->id, 'component_id' => $component->id],
                    [
                        'line_number' => $lineNumber++,
                        'quantity' => rand(1, 5),
                        'uom_id' => $uomId,
                        'scrap_percentage' => rand(0, 10),
                        'is_optional' => false,
                        'is_phantom' => false,
                    ]
                );
            }

            // Create Routing
            $routingNumber = 'RTG-' . str_pad($routingCounter++, 5, '0', STR_PAD_LEFT);

            $routing = Routing::updateOrCreate(
                ['routing_number' => $routingNumber, 'company_id' => $companyId],
                [
                    'product_id' => $product->id,
                    'name' => $product->name . ' - Standard Routing',
                    'description' => 'Standard production routing for ' . $product->name,
                    'status' => RoutingStatus::ACTIVE,
                    'is_default' => true,
                    'created_by' => $userId,
                ]
            );

            $createdRoutings[$product->id] = $routing;

            // Add Routing Operations
            $operations = [
                [
                    'routing_id' => $routing->id,
                    'work_center_id' => $cuttingStation?->id,
                    'operation_number' => 10,
                    'name' => 'Material Preparation',
                    'description' => 'Cut and prepare raw materials',
                    'setup_time' => 30,
                    'run_time_per_unit' => 5,
                    'queue_time' => 10,
                    'move_time' => 5,
                ],
                [
                    'routing_id' => $routing->id,
                    'work_center_id' => $assemblyStation1?->id,
                    'operation_number' => 20,
                    'name' => 'Primary Assembly',
                    'description' => 'Assemble main components',
                    'setup_time' => 15,
                    'run_time_per_unit' => 10,
                    'queue_time' => 5,
                    'move_time' => 5,
                ],
                [
                    'routing_id' => $routing->id,
                    'work_center_id' => $assemblyStation2?->id,
                    'operation_number' => 30,
                    'name' => 'Secondary Assembly',
                    'description' => 'Final assembly and finishing',
                    'setup_time' => 10,
                    'run_time_per_unit' => 8,
                    'queue_time' => 5,
                    'move_time' => 5,
                ],
                [
                    'routing_id' => $routing->id,
                    'work_center_id' => $qcStation?->id,
                    'operation_number' => 40,
                    'name' => 'Quality Inspection',
                    'description' => 'Final quality check and testing',
                    'setup_time' => 5,
                    'run_time_per_unit' => 3,
                    'queue_time' => 5,
                    'move_time' => 2,
                ],
                [
                    'routing_id' => $routing->id,
                    'work_center_id' => $packagingStation?->id,
                    'operation_number' => 50,
                    'name' => 'Packaging',
                    'description' => 'Final packaging and labeling',
                    'setup_time' => 5,
                    'run_time_per_unit' => 2,
                    'queue_time' => 5,
                    'move_time' => 5,
                ],
            ];

            foreach ($operations as $opData) {
                if ($opData['work_center_id']) {
                    RoutingOperation::updateOrCreate(
                        [
                            'routing_id' => $opData['routing_id'],
                            'operation_number' => $opData['operation_number'],
                        ],
                        $opData
                    );
                }
            }

            $this->command->info("Created BOM and Routing for: {$product->name}");
        }

        // Create Work Orders with different statuses
        $this->command->info('Creating Work Orders...');

        $workOrdersData = [
            // Completed Work Order
            [
                'product_index' => 0,
                'quantity' => 50,
                'priority' => WorkOrderPriority::NORMAL,
                'status' => WorkOrderStatus::COMPLETED,
                'notes' => 'Regular production batch - Completed successfully',
                'planned_start' => now()->subDays(10),
                'planned_end' => now()->subDays(5),
                'actual_start' => now()->subDays(10),
                'actual_end' => now()->subDays(6),
                'quantity_completed' => 50,
            ],
            // In Progress Work Order
            [
                'product_index' => 1,
                'quantity' => 100,
                'priority' => WorkOrderPriority::HIGH,
                'status' => WorkOrderStatus::IN_PROGRESS,
                'notes' => 'Urgent customer order - Priority production',
                'planned_start' => now()->subDays(2),
                'planned_end' => now()->addDays(3),
                'actual_start' => now()->subDays(2),
                'actual_end' => null,
                'quantity_completed' => 45,
            ],
            // Released Work Order (ready to start)
            [
                'product_index' => 2,
                'quantity' => 75,
                'priority' => WorkOrderPriority::NORMAL,
                'status' => WorkOrderStatus::RELEASED,
                'notes' => 'Stock replenishment order',
                'planned_start' => now()->addDays(1),
                'planned_end' => now()->addDays(5),
                'actual_start' => null,
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
            // Draft Work Order
            [
                'product_index' => 0,
                'quantity' => 200,
                'priority' => WorkOrderPriority::LOW,
                'status' => WorkOrderStatus::DRAFT,
                'notes' => 'Planned production for next month',
                'planned_start' => now()->addDays(14),
                'planned_end' => now()->addDays(21),
                'actual_start' => null,
                'actual_end' => null,
                'quantity_completed' => 0,
            ],
            // On Hold Work Order
            [
                'product_index' => 1,
                'quantity' => 30,
                'priority' => WorkOrderPriority::URGENT,
                'status' => WorkOrderStatus::ON_HOLD,
                'notes' => 'On hold - Waiting for component delivery',
                'planned_start' => now()->subDays(3),
                'planned_end' => now()->addDays(2),
                'actual_start' => now()->subDays(3),
                'actual_end' => null,
                'quantity_completed' => 10,
            ],
        ];

        $woCounter = 1;
        $productsArray = $manufacturableProducts->take(3)->values();

        foreach ($workOrdersData as $woData) {
            $product = $productsArray[$woData['product_index']] ?? $productsArray[0];
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
                    'estimated_cost' => $woData['quantity'] * 25.50,
                    'actual_cost' => $woData['quantity_completed'] * 24.00,
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

                // Set operation status based on work order status and progress
                if ($woData['status'] === WorkOrderStatus::COMPLETED) {
                    $opStatus = OperationStatus::COMPLETED;
                    $opQuantityCompleted = $woData['quantity'];
                    $actualStart = $woData['actual_start'];
                    $actualEnd = $woData['actual_end'];
                    $actualSetup = $routingOp->setup_time * 0.95;
                    $actualRun = $routingOp->run_time_per_unit * $woData['quantity'] * 0.98;
                } elseif ($woData['status'] === WorkOrderStatus::IN_PROGRESS) {
                    // First 2 operations completed, 3rd in progress
                    if ($routingOp->operation_number <= 20) {
                        $opStatus = OperationStatus::COMPLETED;
                        $opQuantityCompleted = $woData['quantity_completed'];
                        $actualStart = $woData['actual_start'];
                        $actualEnd = now()->subDays(1);
                        $actualSetup = $routingOp->setup_time;
                        $actualRun = $routingOp->run_time_per_unit * $woData['quantity_completed'];
                    } elseif ($routingOp->operation_number == 30) {
                        $opStatus = OperationStatus::IN_PROGRESS;
                        $opQuantityCompleted = (int)($woData['quantity_completed'] * 0.5);
                        $actualStart = now()->subHours(4);
                    }
                } elseif ($woData['status'] === WorkOrderStatus::ON_HOLD) {
                    // First operation completed
                    if ($routingOp->operation_number == 10) {
                        $opStatus = OperationStatus::COMPLETED;
                        $opQuantityCompleted = $woData['quantity_completed'];
                        $actualStart = $woData['actual_start'];
                        $actualEnd = $woData['actual_start']->copy()->addHours(2);
                        $actualSetup = $routingOp->setup_time;
                        $actualRun = $routingOp->run_time_per_unit * $woData['quantity_completed'];
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
                        'actual_cost' => ($actualSetup + $actualRun) / 60 * ($routingOp->workCenter?->cost_per_hour ?? 50),
                    ]
                );
            }

            // Create Work Order Materials from BOM
            $bomItems = BomItem::where('bom_id', $bom->id)->get();

            foreach ($bomItems as $bomItem) {
                $requiredQty = $bomItem->quantity * $woData['quantity'] * (1 + $bomItem->scrap_percentage / 100);
                $issuedQty = 0;

                // Set issued quantity based on work order status
                if ($woData['status'] === WorkOrderStatus::COMPLETED) {
                    $issuedQty = $requiredQty;
                } elseif (in_array($woData['status'], [WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::ON_HOLD])) {
                    $issuedQty = $requiredQty * ($woData['quantity_completed'] / $woData['quantity']);
                }

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
                        'unit_cost' => rand(5, 25) + (rand(0, 99) / 100),
                        'total_cost' => $issuedQty * (rand(5, 25) + (rand(0, 99) / 100)),
                    ]
                );
            }

            $this->command->info("Created Work Order: {$woNumber} ({$woData['status']->value})");
        }

        $this->command->info('Manufacturing seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('  - Work Centers: ' . WorkCenter::where('company_id', $companyId)->count());
        $this->command->info('  - BOMs: ' . Bom::where('company_id', $companyId)->count());
        $this->command->info('  - Routings: ' . Routing::where('company_id', $companyId)->count());
        $this->command->info('  - Work Orders: ' . WorkOrder::where('company_id', $companyId)->count());
    }
}
