<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemResource extends JsonResource
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
            'delivery_note_id' => $this->delivery_note_id,
            'sales_order_item_id' => $this->sales_order_item_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'lot_number' => $this->lot_number,
            'serial_numbers' => $this->serial_numbers,
            'notes' => $this->notes,

            // Product
            'product' => new ProductListResource($this->whenLoaded('product')),

            // Sales order item
            'sales_order_item' => new SalesOrderItemResource($this->whenLoaded('salesOrderItem')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
