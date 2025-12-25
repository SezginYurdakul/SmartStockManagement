<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierListResource extends JsonResource
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
            'supplier_code' => $this->supplier_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
            'payment_terms_days' => $this->payment_terms_days,
            'lead_time_days' => $this->lead_time_days,
            'rating' => $this->rating,
            'is_active' => $this->is_active,
            'contact_person' => $this->contact_person,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
