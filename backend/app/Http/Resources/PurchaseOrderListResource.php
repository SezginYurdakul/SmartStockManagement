<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderListResource extends JsonResource
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
            'order_number' => $this->order_number,
            'supplier' => $this->when($this->relationLoaded('supplier') && $this->supplier, fn() => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'supplier_code' => $this->supplier->supplier_code,
            ]),
            'warehouse' => $this->when($this->relationLoaded('warehouse') && $this->warehouse, fn() => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'status' => $this->status,
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'items_count' => $this->whenCounted('items'),
            'receiving_progress' => $this->when($this->relationLoaded('items'), fn() => $this->receiving_progress),
            'is_overdue' => $this->expected_delivery_date && $this->expected_delivery_date->isPast() && in_array($this->status, ['sent', 'partially_received']),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
