<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpRunResource extends JsonResource
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

            // Planning parameters
            'planning_horizon_start' => $this->planning_horizon_start?->toDateString(),
            'planning_horizon_end' => $this->planning_horizon_end?->toDateString(),
            'planning_horizon_days' => $this->planning_horizon_days,
            'include_safety_stock' => $this->include_safety_stock,
            'respect_lead_times' => $this->respect_lead_times,
            'consider_wip' => $this->consider_wip,
            'net_change' => $this->net_change,

            // Filters
            'product_filters' => $this->product_filters,
            'warehouse_filters' => $this->warehouse_filters,

            // Status
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),

            // Timing
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'duration' => $this->duration,
            'duration_formatted' => $this->duration_formatted,

            // Statistics
            'products_processed' => $this->products_processed,
            'recommendations_generated' => $this->recommendations_generated,
            'warnings_count' => $this->warnings_count,
            'warnings_summary' => $this->warnings_summary,
            'pending_recommendations_count' => $this->when(
                $this->relationLoaded('recommendations'),
                fn() => $this->getPendingRecommendationsCount()
            ),
            'actioned_recommendations_count' => $this->when(
                $this->relationLoaded('recommendations'),
                fn() => $this->getActionedRecommendationsCount()
            ),

            // Error
            'error_message' => $this->error_message,

            // Relationships
            'creator' => new UserResource($this->whenLoaded('creator')),
            'recommendations' => MrpRecommendationResource::collection($this->whenLoaded('recommendations')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
