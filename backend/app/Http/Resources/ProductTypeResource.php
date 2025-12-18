<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'can_be_purchased' => $this->can_be_purchased,
            'can_be_sold' => $this->can_be_sold,
            'can_be_manufactured' => $this->can_be_manufactured,
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,

            // Counts (only when loaded)
            'products_count' => $this->whenCounted('products'),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
