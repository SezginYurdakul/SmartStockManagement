<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
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
            'delivery_number' => $this->delivery_number,
            'delivery_date' => $this->delivery_date?->toDateString(),

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'can_be_edited' => $this->canBeEdited(),

            // Sales Order
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => new SalesOrderListResource($this->whenLoaded('salesOrder')),

            // Warehouse
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),

            // Shipping
            'shipping_address' => $this->shipping_address,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,

            // Notes
            'notes' => $this->notes,

            // Items
            'items' => DeliveryNoteItemResource::collection($this->whenLoaded('items')),

            // Audit
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
