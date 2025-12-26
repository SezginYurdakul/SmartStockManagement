<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingInspectionResource extends JsonResource
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
            'inspection_number' => $this->inspection_number,
            'goods_received_note_id' => $this->goods_received_note_id,
            'grn_item_id' => $this->grn_item_id,
            'product_id' => $this->product_id,
            'acceptance_rule_id' => $this->acceptance_rule_id,

            // Lot/Batch
            'lot_number' => $this->lot_number,
            'batch_number' => $this->batch_number,

            // Quantities
            'quantity_received' => $this->quantity_received,
            'quantity_inspected' => $this->quantity_inspected,
            'quantity_passed' => $this->quantity_passed,
            'quantity_failed' => $this->quantity_failed,
            'quantity_on_hold' => $this->quantity_on_hold,

            // Result
            'result' => $this->result,
            'result_label' => $this->result_label,
            'disposition' => $this->disposition,
            'disposition_label' => $this->disposition_label,
            'pass_rate' => $this->pass_rate,

            // Details
            'inspection_data' => $this->inspection_data,
            'failure_reason' => $this->failure_reason,
            'notes' => $this->notes,

            // Status flags
            'is_complete' => $this->isComplete(),
            'requires_ncr' => $this->requiresNcr(),

            // Relationships
            'goods_received_note' => new GoodsReceivedNoteResource($this->whenLoaded('goodsReceivedNote')),
            'grn_item' => new GoodsReceivedNoteItemResource($this->whenLoaded('grnItem')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'acceptance_rule' => new AcceptanceRuleResource($this->whenLoaded('acceptanceRule')),
            'inspector' => new UserResource($this->whenLoaded('inspector')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'non_conformance_reports' => NonConformanceReportResource::collection($this->whenLoaded('nonConformanceReports')),

            // Timestamps
            'inspected_at' => $this->inspected_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
