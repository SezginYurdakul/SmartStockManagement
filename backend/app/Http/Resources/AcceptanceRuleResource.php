<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcceptanceRuleResource extends JsonResource
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
            'rule_code' => $this->rule_code,
            'name' => $this->name,
            'description' => $this->description,

            // Scope
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,

            // Inspection configuration
            'inspection_type' => $this->inspection_type,
            'inspection_type_label' => $this->inspection_type_label,
            'sampling_method' => $this->sampling_method,
            'sampling_method_label' => $this->sampling_method_label,
            'sample_size_percentage' => $this->sample_size_percentage,
            'aql_level' => $this->aql_level,
            'aql_value' => $this->aql_value,

            // Criteria
            'criteria' => $this->criteria,

            // Status
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'priority' => $this->priority,

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updater' => new UserResource($this->whenLoaded('updater')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
