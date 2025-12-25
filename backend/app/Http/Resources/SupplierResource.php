<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,

            // Contact
            'email' => $this->email,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'website' => $this->website,

            // Address
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,

            // Contact person
            'contact_person' => $this->contact_person,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            // Financial
            'currency' => $this->currency,
            'payment_terms_days' => $this->payment_terms_days,
            'credit_limit' => $this->credit_limit,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'bank_iban' => $this->bank_iban,
            'bank_swift' => $this->bank_swift,

            // Logistics
            'lead_time_days' => $this->lead_time_days,
            'minimum_order_amount' => $this->minimum_order_amount,
            'shipping_method' => $this->shipping_method,

            // Rating & Notes
            'rating' => $this->rating,
            'notes' => $this->notes,
            'meta_data' => $this->meta_data,

            // Status
            'is_active' => $this->is_active,

            // Relationships
            'products' => SupplierProductResource::collection($this->whenLoaded('products')),
            'creator' => new UserResource($this->whenLoaded('creator')),

            // Computed
            'total_purchase_amount' => $this->when(
                $this->relationLoaded('purchaseOrders'),
                fn() => $this->total_purchase_amount
            ),
            'pending_orders_count' => $this->when(
                $this->relationLoaded('purchaseOrders'),
                fn() => $this->pending_orders_count
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
