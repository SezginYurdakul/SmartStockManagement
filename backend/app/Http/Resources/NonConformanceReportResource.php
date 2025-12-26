<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NonConformanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ncr_number' => $this->ncr_number,
            'title' => $this->title,
            'description' => $this->description,

            // Source
            'source_type' => $this->source_type,
            'receiving_inspection_id' => $this->receiving_inspection_id,

            // Related entities
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,
            'lot_number' => $this->lot_number,
            'batch_number' => $this->batch_number,

            // Quantity and severity
            'quantity_affected' => $this->quantity_affected,
            'unit_of_measure' => $this->unit_of_measure,
            'severity' => $this->severity,
            'severity_label' => $this->severity_label,
            'priority' => $this->priority,

            // Defect details
            'defect_type' => $this->defect_type,
            'defect_type_label' => $this->defect_type_label,
            'root_cause' => $this->root_cause,

            // Disposition
            'disposition' => $this->disposition,
            'disposition_label' => $this->disposition_label,
            'disposition_reason' => $this->disposition_reason,
            'cost_impact' => $this->cost_impact,
            'cost_currency' => $this->cost_currency,

            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_open' => $this->isOpen(),
            'can_be_edited' => $this->canBeEdited(),
            'days_open' => $this->days_open,

            // Attachments
            'attachments' => $this->attachments,

            // Closure
            'closure_notes' => $this->closure_notes,

            // Relationships
            'receiving_inspection' => new ReceivingInspectionResource($this->whenLoaded('receivingInspection')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'disposition_approver' => new UserResource($this->whenLoaded('dispositionApprover')),
            'closer' => new UserResource($this->whenLoaded('closer')),

            // Timestamps
            'reported_at' => $this->reported_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'disposition_at' => $this->disposition_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
