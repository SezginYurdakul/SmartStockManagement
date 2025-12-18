<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_currency' => $this->from_currency,
            'to_currency' => $this->to_currency,
            'rate' => $this->rate,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'source' => $this->source,

            // Relations (only when loaded)
            'from' => new CurrencyResource($this->whenLoaded('fromCurrency')),
            'to' => new CurrencyResource($this->whenLoaded('toCurrency')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
