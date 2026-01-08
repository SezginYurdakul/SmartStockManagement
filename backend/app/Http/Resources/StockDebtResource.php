<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDebtResource extends JsonResource
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
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,
            'product' => [
                'id' => $this->product->id ?? null,
                'name' => $this->product->name ?? null,
                'sku' => $this->product->sku ?? null,
            ],
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => [
                'id' => $this->warehouse->id ?? null,
                'name' => $this->warehouse->name ?? null,
                'code' => $this->warehouse->code ?? null,
            ],
            'stock_movement_id' => $this->stock_movement_id,
            'quantity' => (float) $this->quantity,
            'reconciled_quantity' => (float) $this->reconciled_quantity,
            'outstanding_quantity' => (float) $this->outstanding_quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference' => $this->when($this->reference, $this->reference),
            'is_fully_reconciled' => $this->isFullyReconciled(),
            'reconciled_at' => $this->reconciled_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
