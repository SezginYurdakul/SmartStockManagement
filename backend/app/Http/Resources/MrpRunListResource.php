<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpRunListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'name' => $this->name,
            'planning_horizon_start' => $this->planning_horizon_start?->toDateString(),
            'planning_horizon_end' => $this->planning_horizon_end?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'products_processed' => $this->products_processed,
            'recommendations_generated' => $this->recommendations_generated,
            'completed_at' => $this->completed_at?->toISOString(),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
