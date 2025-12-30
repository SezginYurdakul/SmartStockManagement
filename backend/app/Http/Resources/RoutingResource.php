<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routing_number' => $this->routing_number,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,

            // Status
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

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
            'operations' => RoutingOperationResource::collection($this->whenLoaded('operations')),
            'creator' => new UserResource($this->whenLoaded('creator')),

            // Computed
            'operations_count' => $this->when(isset($this->operations_count), $this->operations_count),
            'operation_count' => $this->whenLoaded('operations', fn() => $this->operation_count),
            'total_lead_time' => $this->whenLoaded('operations', fn() => $this->total_lead_time),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
