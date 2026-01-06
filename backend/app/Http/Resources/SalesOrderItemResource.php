<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
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
            'sales_order_id' => $this->sales_order_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity_ordered,
            'quantity_ordered' => $this->quantity_ordered,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'quantity_shipped' => $this->quantity_shipped,
            'quantity_remaining' => $this->quantity_ordered - $this->quantity_shipped,
            'notes' => $this->notes,

            // Product
            'product' => new ProductListResource($this->whenLoaded('product')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
