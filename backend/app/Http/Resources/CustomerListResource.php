<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
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
            'code' => $this->customer_code,
            'customer_code' => $this->customer_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'is_active' => $this->is_active,
            'customer_group_id' => $this->customer_group_id,
            'customer_group' => new CustomerGroupListResource($this->whenLoaded('customerGroup')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
