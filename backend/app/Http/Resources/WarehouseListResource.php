<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Lightweight version for list views.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'warehouse_type' => $this->warehouse_type,
            'warehouse_type_label' => $this->type_label,
            'city' => $this->city,
            'country' => $this->country,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'stocks_count' => $this->when(isset($this->stocks_count), $this->stocks_count),
        ];
    }
}
