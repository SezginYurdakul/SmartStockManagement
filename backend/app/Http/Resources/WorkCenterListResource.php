<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkCenterListResource extends JsonResource
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
            'work_center_type' => $this->work_center_type?->value,
            'work_center_type_label' => $this->work_center_type?->label(),
            'cost_per_hour' => (float) $this->cost_per_hour,
            'capacity_per_day' => (float) $this->capacity_per_day,
            'efficiency_percentage' => (float) $this->efficiency_percentage,
            'is_active' => $this->is_active,
        ];
    }
}
