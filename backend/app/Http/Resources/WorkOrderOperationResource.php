<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderOperationResource extends JsonResource
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

            // Status
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'can_start' => $this->canStart(),
            'can_complete' => $this->canComplete(),

            // Quantities
            'quantity_completed' => (float) $this->quantity_completed,
            'quantity_scrapped' => (float) $this->quantity_scrapped,

            // Planned times
            'planned_start' => $this->planned_start?->toISOString(),
            'planned_end' => $this->planned_end?->toISOString(),

            // Actual times
            'actual_start' => $this->actual_start?->toISOString(),
            'actual_end' => $this->actual_end?->toISOString(),

            // Time spent (in minutes)
            'actual_setup_time' => (float) $this->actual_setup_time,
            'actual_run_time' => (float) $this->actual_run_time,
            'total_actual_time' => (float) $this->total_actual_time,

            // Cost
            'actual_cost' => (float) $this->actual_cost,

            // Efficiency
            'efficiency' => $this->efficiency,

            // Notes
            'notes' => $this->notes,

            // Relationships
            'work_center' => new WorkCenterListResource($this->whenLoaded('workCenter')),
            'starter' => new UserResource($this->whenLoaded('starter')),
            'completer' => new UserResource($this->whenLoaded('completer')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
