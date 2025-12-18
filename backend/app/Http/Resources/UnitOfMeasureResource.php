<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOfMeasureResource extends JsonResource
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
            'uom_type' => $this->uom_type,
            'base_unit_id' => $this->base_unit_id,
            'conversion_factor' => $this->conversion_factor,
            'precision' => $this->precision,
            'is_active' => $this->is_active,
            'is_base_unit' => $this->isBaseUnit(),

            // Relations (only when loaded)
            'base_unit' => new UnitOfMeasureResource($this->whenLoaded('baseUnit')),
            'derived_units' => UnitOfMeasureResource::collection($this->whenLoaded('derivedUnits')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
