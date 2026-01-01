<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routing_number' => $this->routing_number,
            'version' => $this->version,
            'name' => $this->name,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_default' => $this->is_default,
            'product' => new ProductListResource($this->whenLoaded('product')),
            'operations_count' => $this->when(isset($this->operations_count), $this->operations_count),
            'effective_date' => $this->effective_date?->toDateString(),
        ];
    }
}
