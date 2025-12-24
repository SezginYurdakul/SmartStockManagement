<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
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
            'sku' => $this->sku,
            'name' => $this->name,
            'supplier_sku' => $this->pivot->supplier_sku,
            'unit_price' => $this->pivot->unit_price,
            'currency' => $this->pivot->currency,
            'minimum_order_qty' => $this->pivot->minimum_order_qty,
            'lead_time_days' => $this->pivot->lead_time_days,
            'is_preferred' => $this->pivot->is_preferred,
            'is_active' => $this->pivot->is_active,
        ];
    }
}
