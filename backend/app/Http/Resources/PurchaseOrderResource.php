<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
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

            // Relationships
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierListResource($this->whenLoaded('supplier')),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseListResource($this->whenLoaded('warehouse')),

            // Dates
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'actual_delivery_date' => $this->actual_delivery_date?->toDateString(),

            // Status
            'status' => $this->status,
            'can_be_edited' => $this->canBeEdited(),
            'can_be_approved' => $this->canBeApproved(),
            'can_be_sent' => $this->canBeSent(),
            'can_receive_goods' => $this->canReceiveGoods(),
            'can_be_cancelled' => $this->canBeCancelled(),

            // Currency
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,

            // Amounts
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'shipping_cost' => $this->shipping_cost,
            'other_charges' => $this->other_charges,
            'total_amount' => $this->total_amount,

            // Payment & Shipping
            'payment_terms' => $this->payment_terms,
            'payment_due_days' => $this->payment_due_days,
            'shipping_method' => $this->shipping_method,
            'shipping_address' => $this->shipping_address,

            // Notes
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,
            'meta_data' => $this->meta_data,

            // Items
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            // GRN
            'goods_received_notes' => GoodsReceivedNoteResource::collection($this->whenLoaded('goodsReceivedNotes')),

            // Progress
            'total_quantity_ordered' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_ordered),
            'total_quantity_received' => $this->when($this->relationLoaded('items'), fn() => $this->total_quantity_received),
            'remaining_quantity' => $this->when($this->relationLoaded('items'), fn() => $this->remaining_quantity),
            'receiving_progress' => $this->when($this->relationLoaded('items'), fn() => $this->receiving_progress),

            // Approval
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'approved_at' => $this->approved_at?->toISOString(),

            // Audit
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
