<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpRecommendationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Type and status
            'recommendation_type' => $this->recommendation_type?->value,
            'recommendation_type_label' => $this->recommendation_type?->label(),
            'recommendation_type_color' => $this->recommendation_type?->color(),
            'recommendation_type_icon' => $this->recommendation_type?->icon(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),

            // Dates
            'required_date' => $this->required_date?->toDateString(),
            'suggested_date' => $this->suggested_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'days_until_required' => $this->days_until_required,

            // Quantities
            'gross_requirement' => (float) $this->gross_requirement,
            'net_requirement' => (float) $this->net_requirement,
            'suggested_quantity' => (float) $this->suggested_quantity,
            'current_stock' => (float) $this->current_stock,
            'projected_stock' => (float) $this->projected_stock,

            // Demand source
            'demand_source_type' => $this->demand_source_type,
            'demand_source_id' => $this->demand_source_id,

            // Priority and urgency
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'priority_color' => $this->priority?->color(),
            'is_urgent' => $this->is_urgent,
            'is_overdue' => $this->is_overdue,
            'urgency_reason' => $this->urgency_reason,

            // Actions
            'can_action' => $this->status?->canAction(),
            'can_approve' => $this->status?->canApprove(),
            'can_reject' => $this->status?->canReject(),
            'actioned_at' => $this->actioned_at?->toISOString(),
            'action_reference_type' => $this->action_reference_type,
            'action_reference_id' => $this->action_reference_id,
            'action_notes' => $this->action_notes,

            // Calculation details (for debugging/audit)
            'calculation_details' => $this->calculation_details,

            // Summary
            'summary' => $this->getSummary(),

            // Relationships
            'product' => new ProductListResource($this->whenLoaded('product')),
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),
            'actioned_by' => new UserResource($this->whenLoaded('actionedByUser')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
