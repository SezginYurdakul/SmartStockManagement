<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkCenterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            // Type
            'work_center_type' => $this->work_center_type?->value,
            'work_center_type_label' => $this->work_center_type?->label(),

            // Costing
            'cost_per_hour' => (float) $this->cost_per_hour,
            'cost_currency' => $this->cost_currency,

            // Capacity
            'capacity_per_day' => (float) $this->capacity_per_day,
            'efficiency_percentage' => (float) $this->efficiency_percentage,
            'effective_capacity' => (float) $this->effective_capacity,

            // Status
            'is_active' => $this->is_active,

            // Settings
            'settings' => $this->settings,

            // Relationships
            'creator' => new UserResource($this->whenLoaded('creator')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
