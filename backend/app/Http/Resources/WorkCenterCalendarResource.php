<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkCenterCalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_center_id' => $this->work_center_id,
            'calendar_date' => $this->calendar_date?->toDateString(),
            'day_name' => $this->calendar_date?->format('l'),

            // Shift times
            'shift_start' => $this->shift_start,
            'shift_end' => $this->shift_end,
            'break_hours' => (float) $this->break_hours,

            // Capacity
            'available_hours' => (float) $this->available_hours,
            'effective_hours' => (float) $this->effective_hours,
            'efficiency_override' => $this->efficiency_override ? (float) $this->efficiency_override : null,
            'capacity_override' => $this->capacity_override ? (float) $this->capacity_override : null,

            // Day type
            'day_type' => $this->day_type?->value,
            'day_type_label' => $this->day_type?->label(),
            'day_type_color' => $this->day_type?->color(),
            'is_available' => $this->isAvailable(),
            'has_reduced_capacity' => $this->hasReducedCapacity(),

            // Notes
            'notes' => $this->notes,

            // Relationships
            'work_center' => $this->when(
                $this->relationLoaded('workCenter'),
                fn() => [
                    'id' => $this->workCenter->id,
                    'code' => $this->workCenter->code,
                    'name' => $this->workCenter->name,
                ]
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
