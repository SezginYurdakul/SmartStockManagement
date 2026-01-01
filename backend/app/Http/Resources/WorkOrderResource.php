<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,

            // Quantities
            'quantity_ordered' => (float) $this->quantity_ordered,
            'quantity_completed' => (float) $this->quantity_completed,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'remaining_quantity' => (float) $this->remaining_quantity,
            'completion_percentage' => (float) $this->completion_percentage,

            // Status
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),

            // Capabilities
            'can_edit' => $this->canEdit(),
            'can_release' => $this->canRelease(),
            'can_start' => $this->canStart(),
            'can_complete' => $this->canComplete(),
            'can_cancel' => $this->canCancel(),
            'can_issue_materials' => $this->canIssueMaterials(),
            'can_receive_finished_goods' => $this->canReceiveFinishedGoods(),

            // Dates
            'planned_start_date' => $this->planned_start_date?->toISOString(),
            'planned_end_date' => $this->planned_end_date?->toISOString(),
            'actual_start_date' => $this->actual_start_date?->toISOString(),
            'actual_end_date' => $this->actual_end_date?->toISOString(),

            // Cost
            'estimated_cost' => (float) $this->estimated_cost,
            'actual_cost' => (float) $this->actual_cost,

            // Notes
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,
            'meta_data' => $this->meta_data,

            // Relationships
            'product' => new ProductListResource($this->whenLoaded('product')),
            'bom' => new BomListResource($this->whenLoaded('bom')),
            'routing' => new RoutingListResource($this->whenLoaded('routing')),
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'operations' => WorkOrderOperationResource::collection($this->whenLoaded('operations')),
            'materials' => WorkOrderMaterialResource::collection($this->whenLoaded('materials')),

            // Users
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'releaser' => new UserResource($this->whenLoaded('releaser')),

            // Approval dates
            'approved_at' => $this->approved_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            // Progress
            'operations_progress' => $this->whenLoaded('operations', fn() => $this->operations_progress),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
