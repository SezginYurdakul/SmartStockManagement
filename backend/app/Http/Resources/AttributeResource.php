<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'is_filterable' => $this->is_filterable,
            'is_required' => $this->is_required,
            'is_variant' => $this->is_variant,
            'sort_order' => $this->sort_order,

            // Pivot data (when accessed through category or product)
            'pivot' => $this->whenPivotLoaded('category_attributes', fn() => [
                'is_required' => $this->pivot->is_required,
                'order' => $this->pivot->order,
            ]),

            // Relations (only when loaded)
            'values' => AttributeValueResource::collection($this->whenLoaded('values')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
