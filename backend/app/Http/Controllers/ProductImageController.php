<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    protected $imageService;

    public function __construct(ProductImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Upload product images
     */
    public function upload(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_primary' => 'nullable|array',
            'alt_text' => 'nullable|array',
        ]);

        $uploadedImages = $this->imageService->uploadImages(
            $product,
            $request->file('images'),
            [
                'is_primary' => $request->is_primary ?? [],
                'alt_text' => $request->alt_text ?? [],
            ]
        );

        return response()->json([
            'message' => 'Images uploaded successfully',
            'data' => $uploadedImages
        ], 201);
    }

    /**
     * Update image details
     */
    public function update(Request $request, Product $product, ProductImage $image)
    {
        if (!$this->imageService->validateImageBelongsToProduct($image, $product)) {
            return response()->json([
                'message' => 'Image not found for this product'
            ], 404);
        }

        $validated = $request->validate([
            'order' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
            'alt_text' => 'nullable|string',
        ]);

        $updatedImage = $this->imageService->updateImage($image, $validated);

        return response()->json([
            'message' => 'Image updated successfully',
            'data' => $updatedImage
        ]);
    }

    /**
     * Delete product image
     */
    public function destroy(Product $product, ProductImage $image)
    {
        if (!$this->imageService->validateImageBelongsToProduct($image, $product)) {
            return response()->json([
                'message' => 'Image not found for this product'
            ], 404);
        }

        $this->imageService->deleteImage($image);

        return response()->json([
            'message' => 'Image deleted successfully'
        ]);
    }

    /**
     * Reorder images
     */
    public function reorder(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*.id' => 'required|exists:product_images,id',
            'images.*.order' => 'required|integer|min:0',
        ]);

        $this->imageService->reorderImages($product, $request->images);

        return response()->json([
            'message' => 'Images reordered successfully'
        ]);
    }
}
