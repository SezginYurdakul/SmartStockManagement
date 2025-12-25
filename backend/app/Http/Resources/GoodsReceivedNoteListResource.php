<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedNoteListResource extends JsonResource
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
            'purchase_order' => $this->when($this->relationLoaded('purchaseOrder') && $this->purchaseOrder, fn() => [
                'id' => $this->purchaseOrder->id,
                'order_number' => $this->purchaseOrder->order_number,
            ]),
            'supplier' => $this->when($this->relationLoaded('supplier') && $this->supplier, fn() => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'warehouse' => $this->when($this->relationLoaded('warehouse') && $this->warehouse, fn() => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'received_date' => $this->received_date?->toDateString(),
            'status' => $this->status,
            'requires_inspection' => $this->requires_inspection,
            'items_count' => $this->whenCounted('items'),
            'total_quantity_received' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_received),
            'total_cost' => $this->when($this->relationLoaded('items'), fn() => $this->total_cost),
            'received_by' => $this->when($this->relationLoaded('receivedBy'), [
                'id' => $this->receivedBy?->id,
                'name' => $this->receivedBy?->first_name . ' ' . $this->receivedBy?->last_name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
