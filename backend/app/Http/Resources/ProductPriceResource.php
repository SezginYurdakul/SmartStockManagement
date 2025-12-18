<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'price_type' => $this->price_type,
            'unit_price' => $this->unit_price,
            'min_quantity' => $this->min_quantity,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'formatted' => $this->formatted(),

            // Relations (only when loaded)
            'currency' => new CurrencyResource($this->whenLoaded('currency')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
