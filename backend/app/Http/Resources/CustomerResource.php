<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_number' => $this->tax_number,

            // Address
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,

            // Contact
            'contact_person' => $this->contact_person,

            // Financial
            'payment_terms_days' => $this->payment_terms_days,
            'credit_limit' => $this->credit_limit,

            // Notes
            'notes' => $this->notes,

            // Status
            'is_active' => $this->is_active,

            // Customer group
            'customer_group_id' => $this->customer_group_id,
            'customer_group' => new CustomerGroupListResource($this->whenLoaded('customerGroup')),

            // Recent orders
            'sales_orders' => SalesOrderListResource::collection($this->whenLoaded('salesOrders')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
