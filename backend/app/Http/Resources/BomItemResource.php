<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'quantity' => (float) $this->quantity,
            'scrap_percentage' => (float) $this->scrap_percentage,
            'is_optional' => $this->is_optional,
            'is_phantom' => $this->is_phantom,
            'notes' => $this->notes,

            // Relationships
            'component' => new ProductListResource($this->whenLoaded('component')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
