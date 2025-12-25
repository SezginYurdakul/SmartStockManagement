<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
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
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'lot_number' => $this->lot_number,
            'serial_number' => $this->serial_number,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'quantity_available' => (float) $this->quantity_available,
            'unit_cost' => (float) $this->unit_cost,
            'total_value' => (float) $this->total_value,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'received_date' => $this->received_date?->toDateString(),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'notes' => $this->notes,
            'product' => $this->when(
                $this->relationLoaded('product'),
                fn () => [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'low_stock_threshold' => $this->product->low_stock_threshold,
                ]
            ),
            'warehouse' => $this->when(
                $this->relationLoaded('warehouse'),
                fn () => [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code,
                ]
            ),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
