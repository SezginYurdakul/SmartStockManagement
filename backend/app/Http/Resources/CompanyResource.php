<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'base_currency' => $this->base_currency,
            'supported_currencies' => $this->supported_currencies,
            'timezone' => $this->timezone,
            'fiscal_year_start' => $this->fiscal_year_start?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'settings' => $this->settings,

            // Counts (only when loaded)
            'users_count' => $this->whenCounted('users'),
            'products_count' => $this->whenCounted('products'),
            'categories_count' => $this->whenCounted('categories'),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
