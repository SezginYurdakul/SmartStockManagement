<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'can_be_edited' => $this->canBeEdited(),

            // Customer
            'customer_id' => $this->customer_id,
            'customer' => new CustomerListResource($this->whenLoaded('customer')),

            // Addresses
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,

            // Amounts
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,

            // Notes
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,

            // Items
            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),

            // Delivery notes
            'delivery_notes' => DeliveryNoteListResource::collection($this->whenLoaded('deliveryNotes')),

            // Audit
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'approved_by' => new UserResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at?->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
