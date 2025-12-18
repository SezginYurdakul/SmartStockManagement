<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for product listings (index, search)
 */
class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_low_stock' => $this->isLowStock(),

            // Minimal relations
            'primary_category' => $this->whenLoaded('categories', function () {
                $primary = $this->categories->firstWhere('pivot.is_primary', true);
                return $primary ? [
                    'id' => $primary->id,
                    'name' => $primary->name,
                    'slug' => $primary->slug,
                ] : null;
            }),
            'primary_image' => $this->whenLoaded('primaryImage', fn() => [
                'id' => $this->primaryImage?->id,
                'url' => $this->primaryImage?->url,
                'thumbnail_url' => $this->primaryImage?->thumbnail_url,
            ]),
            'variants_count' => $this->whenCounted('variants'),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
