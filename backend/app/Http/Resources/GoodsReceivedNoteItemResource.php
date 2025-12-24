<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedNoteItemResource extends JsonResource
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
            'line_number' => $this->line_number,

            // References
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'purchase_order_item' => $this->when($this->relationLoaded('purchaseOrderItem'), [
                'id' => $this->purchaseOrderItem->id,
                'quantity_ordered' => $this->purchaseOrderItem->quantity_ordered,
                'quantity_received' => $this->purchaseOrderItem->quantity_received,
            ]),

            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ]),

            // Quantities
            'quantity_received' => $this->quantity_received,
            'quantity_accepted' => $this->quantity_accepted,
            'quantity_rejected' => $this->quantity_rejected,
            'pending_quantity' => $this->pending_quantity,
            'is_fully_accepted' => $this->is_fully_accepted,
            'is_fully_rejected' => $this->is_fully_rejected,

            // UOM
            'uom_id' => $this->uom_id,
            'uom' => $this->when($this->relationLoaded('unitOfMeasure'), [
                'id' => $this->unitOfMeasure->id,
                'code' => $this->unitOfMeasure->code,
                'name' => $this->unitOfMeasure->name,
            ]),

            // Cost
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,

            // Tracking
            'lot_number' => $this->lot_number,
            'serial_number' => $this->serial_number,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'manufacture_date' => $this->manufacture_date?->toDateString(),

            // Storage
            'storage_location' => $this->storage_location,
            'bin_location' => $this->bin_location,

            // Inspection
            'inspection_status' => $this->inspection_status,
            'inspection_notes' => $this->inspection_notes,
            'rejection_reason' => $this->rejection_reason,

            'notes' => $this->notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
