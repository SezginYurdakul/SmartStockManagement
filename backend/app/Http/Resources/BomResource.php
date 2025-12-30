<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_number' => $this->bom_number,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,

            // Type and Status
            'bom_type' => $this->bom_type?->value,
            'bom_type_label' => $this->bom_type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // Quantity
            'quantity' => (float) $this->quantity,
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),

            // Flags
            'is_default' => $this->is_default,
            'can_edit' => $this->canEdit(),
            'can_use_for_production' => $this->canUseForProduction(),

            // Dates
            'effective_date' => $this->effective_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),

            // Notes
            'notes' => $this->notes,
            'meta_data' => $this->meta_data,

            // Relationships
            'product' => new ProductListResource($this->whenLoaded('product')),
            'items' => BomItemResource::collection($this->whenLoaded('items')),
            'creator' => new UserResource($this->whenLoaded('creator')),

            // Computed
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'component_count' => $this->whenLoaded('items', fn() => $this->component_count),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
