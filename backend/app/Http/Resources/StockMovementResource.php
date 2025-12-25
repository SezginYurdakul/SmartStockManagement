<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
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
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'lot_number' => $this->lot_number,
            'serial_number' => $this->serial_number,
            'movement_type' => $this->movement_type,
            'movement_type_label' => $this->movement_type_label,
            'transaction_type' => $this->transaction_type,
            'transaction_type_label' => $this->transaction_type_label,
            'reference_number' => $this->reference_number,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'quantity' => (float) $this->quantity,
            'quantity_before' => (float) $this->quantity_before,
            'quantity_after' => (float) $this->quantity_after,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'is_inbound' => $this->isInbound(),
            'is_outbound' => $this->isOutbound(),
            'notes' => $this->notes,
            'meta_data' => $this->meta_data,
            'product' => $this->when(
                $this->relationLoaded('product'),
                fn () => [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
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
            'from_warehouse' => $this->when(
                $this->relationLoaded('fromWarehouse') && $this->fromWarehouse,
                fn () => [
                    'id' => $this->fromWarehouse->id,
                    'name' => $this->fromWarehouse->name,
                    'code' => $this->fromWarehouse->code,
                ]
            ),
            'to_warehouse' => $this->when(
                $this->relationLoaded('toWarehouse') && $this->toWarehouse,
                fn () => [
                    'id' => $this->toWarehouse->id,
                    'name' => $this->toWarehouse->name,
                    'code' => $this->toWarehouse->code,
                ]
            ),
            'creator' => $this->when(
                $this->relationLoaded('creator') && $this->creator,
                fn () => [
                    'id' => $this->creator->id,
                    'name' => $this->creator->full_name,
                ]
            ),
            'movement_date' => $this->movement_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
