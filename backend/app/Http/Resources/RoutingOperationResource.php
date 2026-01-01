<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingOperationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_number' => $this->operation_number,
            'name' => $this->name,
            'description' => $this->description,

            // Time estimates (in minutes)
            'setup_time' => (float) $this->setup_time,
            'run_time_per_unit' => (float) $this->run_time_per_unit,
            'queue_time' => (float) $this->queue_time,
            'move_time' => (float) $this->move_time,
            'total_time_per_unit' => (float) $this->total_time_per_unit,

            // Subcontracting
            'is_subcontracted' => $this->is_subcontracted,
            'subcontract_cost' => $this->subcontract_cost ? (float) $this->subcontract_cost : null,
            'subcontractor' => new SupplierListResource($this->whenLoaded('subcontractor')),

            // Instructions
            'instructions' => $this->instructions,
            'settings' => $this->settings,

            // Relationships
            'work_center' => new WorkCenterListResource($this->whenLoaded('workCenter')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
