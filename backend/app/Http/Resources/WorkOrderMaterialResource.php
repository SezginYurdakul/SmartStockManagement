<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderMaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Quantities
            'quantity_required' => (float) $this->quantity_required,
            'quantity_issued' => (float) $this->quantity_issued,
            'quantity_returned' => (float) $this->quantity_returned,
            'outstanding_quantity' => (float) $this->outstanding_quantity,
            'net_issued_quantity' => (float) $this->net_issued_quantity,

            // Status
            'is_fully_issued' => $this->isFullyIssued(),
            'has_shortage' => $this->hasShortage(),

            // Cost
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,

            // Notes
            'notes' => $this->notes,

            // Relationships
            'product' => new ProductListResource($this->whenLoaded('product')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
