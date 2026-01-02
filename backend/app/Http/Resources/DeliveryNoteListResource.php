<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteListResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'sales_order' => new SalesOrderListResource($this->whenLoaded('salesOrder')),
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
