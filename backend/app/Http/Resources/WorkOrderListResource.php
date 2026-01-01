<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'quantity_completed' => (float) $this->quantity_completed,
            'completion_percentage' => (float) $this->completion_percentage,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'planned_start_date' => $this->planned_start_date?->toISOString(),
            'planned_end_date' => $this->planned_end_date?->toISOString(),
            'product' => new ProductListResource($this->whenLoaded('product')),
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
