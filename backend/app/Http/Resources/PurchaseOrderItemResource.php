<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ]),
            'description' => $this->description,

            // Quantities
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'quantity_cancelled' => $this->quantity_cancelled,
            'remaining_quantity' => $this->remaining_quantity,
            'is_fully_received' => $this->is_fully_received,
            'receiving_progress' => $this->receiving_progress,

            // UOM
            'uom_id' => $this->uom_id,
            'uom' => $this->when($this->relationLoaded('unitOfMeasure'), [
                'id' => $this->unitOfMeasure->id,
                'code' => $this->unitOfMeasure->code,
                'name' => $this->unitOfMeasure->name,
            ]),

            // Pricing
            'unit_price' => $this->unit_price,
            'discount_percentage' => $this->discount_percentage,
            'discount_amount' => $this->discount_amount,
            'tax_percentage' => $this->tax_percentage,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,

            // Dates
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'actual_delivery_date' => $this->actual_delivery_date?->toDateString(),

            // Tracking
            'lot_number' => $this->lot_number,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
