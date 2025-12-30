<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_number' => $this->bom_number,
            'version' => $this->version,
            'name' => $this->name,
            'bom_type' => $this->bom_type?->value,
            'bom_type_label' => $this->bom_type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_default' => $this->is_default,
            'product' => new ProductListResource($this->whenLoaded('product')),
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'effective_date' => $this->effective_date?->toDateString(),
        ];
    }
}
