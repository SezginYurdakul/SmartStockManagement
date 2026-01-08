<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'stock' => $this->stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            // Negative stock policy
            'negative_stock_policy' => $this->negative_stock_policy,
            'negative_stock_limit' => $this->negative_stock_limit,
            // Reservation policy
            'reservation_policy' => $this->reservation_policy,
            'meta_data' => $this->meta_data,

            // Relations (only when loaded)
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'unit_of_measure' => new UnitOfMeasureResource($this->whenLoaded('unitOfMeasure')),
            'primary_category' => new CategoryResource($this->whenLoaded('primaryCategory', fn() => $this->primaryCategory)),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'variants_count' => $this->whenCounted('variants'),
            'attributes' => ProductAttributeResource::collection($this->whenLoaded('attributes')),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'creator' => new UserResource($this->whenLoaded('creator')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
