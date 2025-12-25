<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedNoteResource extends JsonResource
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
            'grn_number' => $this->grn_number,

            // Relationships
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->when($this->relationLoaded('purchaseOrder'), [
                'id' => $this->purchaseOrder->id,
                'order_number' => $this->purchaseOrder->order_number,
            ]),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierListResource($this->whenLoaded('supplier')),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),

            // Dates
            'received_date' => $this->received_date?->toDateString(),
            'delivery_note_number' => $this->delivery_note_number,
            'delivery_note_date' => $this->delivery_note_date?->toDateString(),
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toDateString(),

            // Status
            'status' => $this->status,
            'can_be_edited' => $this->canBeEdited(),
            'can_be_completed' => $this->canBeCompleted(),

            // Inspection
            'requires_inspection' => $this->requires_inspection,
            'inspected_by' => $this->inspected_by,
            'inspected_by_user' => new UserResource($this->whenLoaded('inspectedBy')),
            'inspected_at' => $this->inspected_at?->toISOString(),
            'inspection_notes' => $this->inspection_notes,

            // Notes
            'notes' => $this->notes,
            'meta_data' => $this->meta_data,

            // Items
            'items' => GoodsReceivedNoteItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            // Totals
            'total_quantity_received' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_received),
            'total_quantity_accepted' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_accepted),
            'total_quantity_rejected' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_rejected),
            'total_cost' => $this->when($this->relationLoaded('items'), fn() => $this->total_cost),

            // Audit
            'received_by' => new UserResource($this->whenLoaded('receivedBy')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
