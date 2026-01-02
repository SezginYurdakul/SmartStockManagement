<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerGroupPriceResource extends JsonResource
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
            'customer_group_id' => $this->customer_group_id,
            'product_id' => $this->product_id,
            'price' => $this->price,
            'currency_id' => $this->currency_id,
            'min_quantity' => $this->min_quantity,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active' => $this->is_active,

            // Relationships
            'product' => new ProductListResource($this->whenLoaded('product')),
            'customer_group' => new CustomerGroupListResource($this->whenLoaded('customerGroup')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
